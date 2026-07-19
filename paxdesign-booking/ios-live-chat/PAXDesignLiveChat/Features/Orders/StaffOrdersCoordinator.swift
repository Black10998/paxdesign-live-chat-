import Foundation
import SwiftUI

struct StaffOrderSummary: Decodable, Identifiable, Hashable {
    let id: Int
    let ref: String
    let service_label: String
    let status: String
    let description: String?
    let customer_name: String
    let customer_email: String
    let created_at: String?

    init(from decoder: Decoder) throws {
        let c = try decoder.container(keyedBy: CodingKeys.self)
        id = CustomerPortalDecode.int(c, .id)
        ref = CustomerPortalDecode.string(c, .ref)
        service_label = CustomerPortalDecode.string(c, .service_label)
        status = CustomerPortalDecode.string(c, .status)
        description = try c.decodeIfPresent(String.self, forKey: .description)
        customer_name = CustomerPortalDecode.string(c, .customer_name)
        customer_email = CustomerPortalDecode.string(c, .customer_email)
        created_at = try c.decodeIfPresent(String.self, forKey: .created_at)
    }

    private enum CodingKeys: String, CodingKey {
        case id, ref, service_label, status, description, customer_name, customer_email, created_at
    }
}

struct StaffOrderDetail: Decodable, Identifiable {
    struct FileItem: Decodable, Identifiable {
        let id: Int
        let file_name: String
        let mime_type: String
        let file_size: Int
        let kind: String
        let visibility: String?
        let created_at: String?
        let download_url: String?

        init(from decoder: Decoder) throws {
            let c = try decoder.container(keyedBy: CodingKeys.self)
            id = CustomerPortalDecode.int(c, .id)
            file_name = CustomerPortalDecode.string(c, .file_name)
            mime_type = CustomerPortalDecode.string(c, .mime_type)
            file_size = CustomerPortalDecode.int(c, .file_size)
            kind = CustomerPortalDecode.string(c, .kind)
            visibility = try c.decodeIfPresent(String.self, forKey: .visibility)
            created_at = try c.decodeIfPresent(String.self, forKey: .created_at)
            download_url = try c.decodeIfPresent(String.self, forKey: .download_url)
        }

        private enum CodingKeys: String, CodingKey {
            case id, file_name, mime_type, file_size, kind, visibility, created_at, download_url
        }
    }
    struct Note: Decodable, Identifiable {
        let id: Int
        let body: String
        let visibility: String?
        let created_at: String?

        init(from decoder: Decoder) throws {
            let c = try decoder.container(keyedBy: CodingKeys.self)
            let bodyValue = try c.decode(String.self, forKey: .body)
            id = CustomerPortalDecode.int(c, .id)
            body = bodyValue
            visibility = try c.decodeIfPresent(String.self, forKey: .visibility)
            created_at = try c.decodeIfPresent(String.self, forKey: .created_at)
        }

        private enum CodingKeys: String, CodingKey {
            case id, body, visibility, created_at
        }
    }

    struct Activity: Decodable, Identifiable {
        let id: Int
        let summary: String
        let event_type: String
        let created_at: String?

        init(from decoder: Decoder) throws {
            let c = try decoder.container(keyedBy: CodingKeys.self)
            let summaryValue = try c.decode(String.self, forKey: .summary)
            let decodedID = CustomerPortalDecode.int(c, .id)
            id = decodedID != 0 ? decodedID : abs(summaryValue.hashValue)
            summary = summaryValue
            event_type = try c.decodeIfPresent(String.self, forKey: .event_type) ?? ""
            created_at = try c.decodeIfPresent(String.self, forKey: .created_at)
        }

        private enum CodingKeys: String, CodingKey {
            case id, summary, event_type, created_at
        }
    }

    struct Assigned: Decodable {
        let user_id: Int
        let display_name: String?
    }

    let id: Int
    let ref: String
    let service_label: String
    let status: String
    let description: String?
    let customer_name: String
    let customer_email: String
    let notes: [Note]?
    let activity: [Activity]?
    let assigned: Assigned?
    let files: [FileItem]?
    let created_at: String?

    init(from decoder: Decoder) throws {
        let c = try decoder.container(keyedBy: CodingKeys.self)
        id = CustomerPortalDecode.int(c, .id)
        ref = CustomerPortalDecode.string(c, .ref)
        service_label = CustomerPortalDecode.string(c, .service_label)
        status = CustomerPortalDecode.string(c, .status)
        description = try c.decodeIfPresent(String.self, forKey: .description)
        customer_name = CustomerPortalDecode.string(c, .customer_name)
        customer_email = CustomerPortalDecode.string(c, .customer_email)
        notes = try c.decodeIfPresent([Note].self, forKey: .notes)
        activity = try c.decodeIfPresent([Activity].self, forKey: .activity)
        assigned = try c.decodeIfPresent(Assigned.self, forKey: .assigned)
        files = try c.decodeIfPresent([FileItem].self, forKey: .files)
        created_at = try c.decodeIfPresent(String.self, forKey: .created_at)
    }

    private enum CodingKeys: String, CodingKey {
        case id, ref, service_label, status, description, customer_name, customer_email, notes, activity, assigned, files, created_at
    }
}

struct StaffOrdersListResponse: Decodable {
    let orders: [StaffOrderSummary]
}

@MainActor
final class StaffOrdersCoordinator: ObservableObject {
    static let shared = StaffOrdersCoordinator()

    @Published private(set) var orders: [StaffOrderSummary] = []
    @Published private(set) var isLoading = false
    @Published private(set) var errorMessage: String?
    @Published var pendingOrderId: Int?

    private static let readOrderIdsKey = "pax.staff.readOrderIds"
    @Published private(set) var readOrderIds: Set<Int> = []

    var unreadCount: Int {
        orders.filter {
            ($0.status == "received" || $0.status == "pending") && !readOrderIds.contains($0.id)
        }.count
    }

    private init() {
        if let stored = UserDefaults.standard.array(forKey: Self.readOrderIdsKey) as? [Int] {
            readOrderIds = Set(stored)
        }
    }

    func markOrderRead(_ orderId: Int) {
        guard orderId > 0 else { return }
        readOrderIds.insert(orderId)
        UserDefaults.standard.set(Array(readOrderIds), forKey: Self.readOrderIdsKey)
    }

    func refresh(auth: AuthStore) async {
        guard let api = auth.api else { return }
        isLoading = orders.isEmpty
        errorMessage = nil
        defer { isLoading = false }
        do {
            orders = try await api.fetchStaffOrders().orders
        } catch {
            errorMessage = error.localizedDescription
        }
    }

    func openOrder(_ orderId: Int) {
        pendingOrderId = orderId
        NotificationCenter.default.post(name: .paxOpenStaffOrder, object: nil, userInfo: ["order_id": orderId])
    }

    func consumePendingOrderId() -> Int? {
        defer { pendingOrderId = nil }
        return pendingOrderId
    }
}

extension Notification.Name {
    static let paxOpenStaffOrder = Notification.Name("paxOpenStaffOrder")
}
