import Foundation
import SwiftUI

@MainActor
final class CustomerNotificationsBadgeStore: ObservableObject {
    static let shared = CustomerNotificationsBadgeStore()

    @Published private(set) var unreadCount = 0

    private var refreshTask: Task<Void, Never>?

    private init() {}

    func refresh(api: CustomerAPIClient) async {
        do {
            let response = try await api.fetchNotifications(unreadOnly: true)
            unreadCount = max(0, response.unread_count)
            UNUserNotificationCenter.current().setBadgeCount(unreadCount) { _ in }
        } catch {
            #if DEBUG
            print("Customer notification badge refresh failed: \(error.localizedDescription)")
            #endif
        }
    }

    func scheduleRefresh(api: CustomerAPIClient) {
        refreshTask?.cancel()
        refreshTask = Task {
            await refresh(api: api)
        }
    }

    func decrementAfterRead() {
        unreadCount = max(0, unreadCount - 1)
        UNUserNotificationCenter.current().setBadgeCount(unreadCount) { _ in }
    }

    func incrementUnread() {
        unreadCount += 1
        UNUserNotificationCenter.current().setBadgeCount(unreadCount) { _ in }
    }

    func clearAfterMarkAllRead() {
        unreadCount = 0
        UNUserNotificationCenter.current().setBadgeCount(0) { _ in }
    }
}

import UserNotifications

struct CustomerNotificationBellButton: View {
    @EnvironmentObject private var api: CustomerAPIClient
    @EnvironmentObject private var navigation: CustomerNavigationCoordinator
    @ObservedObject private var badgeStore = CustomerNotificationsBadgeStore.shared

    var body: some View {
        Button {
            navigation.openNotifications()
            PAXHaptics.light()
        } label: {
            ZStack(alignment: .topTrailing) {
                PAXIcon(badgeStore.unreadCount > 0 ? "bell.badge.fill" : "bell", size: .card, emphasis: .primary)
                    .frame(width: 36, height: 36)
                    .background(.ultraThinMaterial, in: Circle())
                    .overlay(
                        Circle()
                            .stroke(PAXTheme.border.opacity(0.25), lineWidth: 0.5)
                    )
                if badgeStore.unreadCount > 0 {
                    Text(badgeStore.unreadCount > 99 ? "99+" : "\(badgeStore.unreadCount)")
                        .font(.system(size: 9, weight: .bold))
                        .foregroundStyle(.white)
                        .padding(.horizontal, badgeStore.unreadCount > 9 ? 4 : 3)
                        .padding(.vertical, 2)
                        .background(Color.red)
                        .clipShape(Capsule())
                        .offset(x: 6, y: -4)
                }
            }
        }
        .buttonStyle(.plain)
        .accessibilityLabel(
            badgeStore.unreadCount > 0
                ? String(localized: "\(badgeStore.unreadCount) unread notifications")
                : String(localized: "Notifications")
        )
        .task(id: AuthStore.shared.sessionEpoch) {
            guard AuthStore.shared.isLoggedIn, AuthStore.shared.isCustomerSession else { return }
            badgeStore.scheduleRefresh(api: api)
        }
    }
}
