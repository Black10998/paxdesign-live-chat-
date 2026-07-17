import Foundation
import CoreSpotlight
import UniformTypeIdentifiers

@MainActor
enum SpotlightIndexer {
    static func indexAppContent(
        auth: AuthStore,
        coordinator: ChatCoordinator
    ) {
        guard auth.isLoggedIn else {
            CSSearchableIndex.default().deleteAllSearchableItems()
            return
        }

        var items: [CSSearchableItem] = []

        for module in PlatformModule.allCases where PlatformModuleAccess.isAvailable(module, auth: auth) {
            items.append(makeItem(
                id: "module.\(module.rawValue)",
                title: module.title,
                description: module.subtitle,
                keywords: [module.title, module.rawValue, "PAXDesign"]
            ))
        }

        if auth.canViewChats {
            for session in coordinator.sessions.prefix(30) {
                items.append(makeItem(
                    id: "session.\(session.sessionId)",
                    title: session.displayName,
                    description: session.lastPreview.isEmpty ? session.detectedService : session.lastPreview,
                    keywords: [session.displayName, session.detectedService, "chat", "customer"]
                ))
            }
        }

        CSSearchableIndex.default().indexSearchableItems(items)
    }

    private static func makeItem(id: String, title: String, description: String, keywords: [String]) -> CSSearchableItem {
        let attributeSet = CSSearchableItemAttributeSet(itemContentType: UTType.data.identifier)
        attributeSet.title = title
        attributeSet.contentDescription = description
        attributeSet.keywords = keywords
        return CSSearchableItem(uniqueIdentifier: id, domainIdentifier: "at.paxdesign.livechat", attributeSet: attributeSet)
    }
}
