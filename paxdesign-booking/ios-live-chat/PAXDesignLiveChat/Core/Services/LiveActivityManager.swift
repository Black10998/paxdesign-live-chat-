import Foundation

#if canImport(ActivityKit)
import ActivityKit
#endif

@MainActor
final class LiveActivityManager {
    static let shared = LiveActivityManager()

    private init() {}

    func updateLiveRequestCount(_ count: Int, topCustomer: String?) {
        guard #available(iOS 16.2, *) else { return }
        #if canImport(ActivityKit)
        guard ActivityAuthorizationInfo().areActivitiesEnabled else { return }

        if count <= 0 {
            endAll()
            return
        }

        let content = PAXLiveActivityAttributes.ContentState(
            waitingCount: count,
            customerName: topCustomer ?? L10n.LiveNoRequests,
            updatedAt: Date()
        )

        if let activity = Activity<PAXLiveActivityAttributes>.activities.first {
            Task { await activity.update(using: content) }
        } else {
            let attributes = PAXLiveActivityAttributes(siteName: "PAXDesign Live Chat")
            _ = try? Activity.request(attributes: attributes, contentState: content, pushType: nil)
        }
        #endif
    }

    func endAll() {
        guard #available(iOS 16.2, *) else { return }
        #if canImport(ActivityKit)
        Task {
            for activity in Activity<PAXLiveActivityAttributes>.activities {
                await activity.end(dismissalPolicy: .immediate)
            }
        }
        #endif
    }
}

#if canImport(ActivityKit)
@available(iOS 16.2, *)
struct PAXLiveActivityAttributes: ActivityAttributes {
    struct ContentState: Codable, Hashable {
        var waitingCount: Int
        var customerName: String
        var updatedAt: Date
    }

    var siteName: String
}
#endif
