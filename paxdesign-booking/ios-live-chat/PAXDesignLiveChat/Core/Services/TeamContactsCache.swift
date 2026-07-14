import Foundation

@MainActor
final class TeamContactsCache {
    static let shared = TeamContactsCache()

    private var cachedStaff: [StaffMember] = []
    private var cachedAt: Date?
    private var inflight: Task<[StaffMember], Error>?
    private let maxAge: TimeInterval = 60

    private init() {}

    func invalidate() {
        cachedStaff = []
        cachedAt = nil
        inflight?.cancel()
        inflight = nil
    }

    func fetch(auth: AuthStore, force: Bool = false) async throws -> [StaffMember] {
        if !force,
           let cachedAt,
           Date().timeIntervalSince(cachedAt) <= maxAge,
           !cachedStaff.isEmpty {
            return cachedStaff
        }

        if let inflight, !force {
            return try await inflight.value
        }

        guard let api = auth.api else {
            return cachedStaff
        }

        let task = Task<[StaffMember], Error> {
            let response = try await api.fetchTeamContacts()
            let currentId = auth.profile?.userId ?? 0
            return response.staff
                .deduplicatedByUserId()
                .deduplicatedByEmail()
                .deduplicatedByDisplayName()
                .filter { $0.userId != currentId && $0.enabled }
                .sorted { lhs, rhs in
                    let rank: (StaffMember) -> Int = { member in
                        if member.isExecutive { return 0 }
                        if member.isAdministrator { return 1 }
                        if member.permissions.manageUsers { return 2 }
                        return 3
                    }
                    let lr = rank(lhs)
                    let rr = rank(rhs)
                    if lr != rr { return lr < rr }
                    return lhs.displayName.localizedCaseInsensitiveCompare(rhs.displayName) == .orderedAscending
                }
        }
        inflight = task
        defer { inflight = nil }

        do {
            let staff = try await task.value
            cachedStaff = staff
            cachedAt = Date()
            return staff
        } catch {
            if !cachedStaff.isEmpty {
                return cachedStaff
            }
            throw error
        }
    }
}
