import Foundation

struct GlobalSearchResult: Identifiable, Hashable {
    let id: String
    let title: String
    let subtitle: String
    let module: PlatformModule
    let destination: SearchDestination

    enum SearchDestination: Hashable {
        case session(String)
        case module(PlatformModule)
        case task(String)
        case event(String)
        case document(String)
        case activity(String)
    }
}

@MainActor
enum GlobalSearchService {
    static func search(
        query: String,
        auth: AuthStore,
        coordinator: ChatCoordinator,
        teamCoordinator: TeamMessagingCoordinator
    ) async -> [GlobalSearchResult] {
        let q = query.trimmingCharacters(in: .whitespacesAndNewlines)
        guard !q.isEmpty else { return [] }

        if let api = auth.api, let response = try? await api.platformSearch(query: q) {
            return mapServerResults(response.results, auth: auth)
        }

        return localSearch(
            query: q,
            auth: auth,
            coordinator: coordinator,
            teamCoordinator: teamCoordinator
        )
    }

    private static func mapServerResults(_ hits: [PlatformSearchHit], auth: AuthStore) -> [GlobalSearchResult] {
        hits.compactMap { hit in
            let module = PlatformModule(rawValue: hit.module) ?? moduleForType(hit.type)
            guard PlatformModuleAccess.isAvailable(module, auth: auth) else { return nil }

            let destination: GlobalSearchResult.SearchDestination
            switch hit.type {
            case "session":
                destination = .session(hit.id)
            case "task":
                destination = .task(hit.id)
            case "event":
                destination = .event(hit.id)
            case "document":
                destination = .document(hit.id)
            case "activity":
                destination = .activity(hit.id)
            default:
                destination = .module(module)
            }

            return GlobalSearchResult(
                id: "\(hit.type)-\(hit.id)",
                title: hit.title,
                subtitle: hit.subtitle,
                module: module,
                destination: destination
            )
        }
    }

    private static func moduleForType(_ type: String) -> PlatformModule {
        switch type {
        case "task": return .tasks
        case "event": return .calendar
        case "document": return .files
        case "activity": return .activityLog
        case "session": return .chats
        default: return .dashboard
        }
    }

    private static func localSearch(
        query: String,
        auth: AuthStore,
        coordinator: ChatCoordinator,
        teamCoordinator: TeamMessagingCoordinator
    ) -> [GlobalSearchResult] {
        let q = query.lowercased()
        var results: [GlobalSearchResult] = []

        if auth.canViewChats {
            for session in coordinator.sessions where !session.isTeamDM {
                if matches(session.displayName, q) || matches(session.lastPreview, q) || matches(session.detectedService, q) {
                    results.append(GlobalSearchResult(
                        id: "session-\(session.sessionId)",
                        title: session.displayName,
                        subtitle: session.lastPreview.isEmpty ? session.detectedService : session.lastPreview,
                        module: .chats,
                        destination: .session(session.sessionId)
                    ))
                }
            }
            for session in coordinator.sessions.filter(\.isTeamDM) + teamCoordinator.teamSessions {
                if matches(session.displayName, q) || matches(session.lastPreview, q) {
                    results.append(GlobalSearchResult(
                        id: "team-\(session.sessionId)",
                        title: session.displayName,
                        subtitle: session.lastPreview.isEmpty ? L10n.TeamChatPlaceholder : session.lastPreview,
                        module: .team,
                        destination: .session(session.sessionId)
                    ))
                }
            }
        }

        for module in PlatformModule.allCases where PlatformModuleAccess.isAvailable(module, auth: auth) {
            if matches(module.title, q) || matches(module.subtitle, q) {
                results.append(GlobalSearchResult(
                    id: "module-\(module.rawValue)",
                    title: module.title,
                    subtitle: module.subtitle,
                    module: module,
                    destination: .module(module)
                ))
            }
        }

        if auth.canViewTasks {
            for task in TaskStore.shared.tasks where matches(task.title, q) || matches(task.notes, q) {
                results.append(GlobalSearchResult(
                    id: "task-\(task.id)",
                    title: task.title,
                    subtitle: task.notes,
                    module: .tasks,
                    destination: .task(task.id)
                ))
            }
        }

        if auth.canViewCalendar {
            for event in CalendarStore.shared.events where matches(event.title, q) || matches(event.notes, q) {
                results.append(GlobalSearchResult(
                    id: "event-\(event.id)",
                    title: event.title,
                    subtitle: MessageTimeFormatter.relativeUpdatedLabel(from: event.startDate),
                    module: .calendar,
                    destination: .event(event.id)
                ))
            }
        }

        if auth.canViewFiles {
            for doc in FileLibraryStore.shared.documents where matches(doc.name, q) || matches(doc.detail, q) {
                results.append(GlobalSearchResult(
                    id: "doc-\(doc.id)",
                    title: doc.name,
                    subtitle: doc.category.title,
                    module: .files,
                    destination: .document(doc.id)
                ))
            }
        }

        if auth.canViewActivityLog {
            for entry in ActivityLogService.shared.entries where matches(entry.title, q) || matches(entry.detail, q) {
                results.append(GlobalSearchResult(
                    id: "activity-\(entry.id)",
                    title: entry.title,
                    subtitle: entry.detail,
                    module: .activityLog,
                    destination: .activity(entry.id)
                ))
            }
        }

        return Array(results.prefix(40))
    }

    private static func matches(_ value: String, _ query: String) -> Bool {
        !value.isEmpty && value.lowercased().contains(query)
    }
}
