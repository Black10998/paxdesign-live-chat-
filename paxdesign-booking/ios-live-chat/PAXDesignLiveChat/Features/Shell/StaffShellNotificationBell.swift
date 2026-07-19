import SwiftUI

struct StaffShellNotificationBell: View {
    @EnvironmentObject private var auth: AuthStore
    @EnvironmentObject private var coordinator: ChatCoordinator
    @ObservedObject private var orders = StaffOrdersCoordinator.shared
    @State private var showCenter = false

    private var totalUnread: Int {
        coordinator.unreadChatCount + coordinator.unreadTeamCount + coordinator.liveCount + orders.unreadCount
    }

    var body: some View {
        Button {
            showCenter = true
            PAXHaptics.light()
        } label: {
            PAXIcon("bell", size: .row)
                .overlay(alignment: .topTrailing) {
                    if totalUnread > 0 {
                        Text(totalUnread > 99 ? "99+" : "\(totalUnread)")
                            .font(.system(size: 9, weight: .bold))
                            .foregroundStyle(.white)
                            .padding(.horizontal, totalUnread > 9 ? 4 : 3)
                            .padding(.vertical, 1)
                            .background(Color.red)
                            .clipShape(Capsule())
                            .offset(x: 6, y: -6)
                    }
                }
        }
        .buttonStyle(.plain)
        .accessibilityLabel(
            totalUnread > 0
                ? "\(L10n.NotificationsCenterTitle), \(totalUnread) unread"
                : L10n.NotificationsCenterTitle
        )
        .sheet(isPresented: $showCenter) {
            NavigationStack {
                NotificationsCenterView()
            }
            .environmentObject(auth)
            .environmentObject(coordinator)
        }
        .task {
            await orders.refresh(auth: auth)
        }
    }
}

struct StaffShellNotificationOverlay: ViewModifier {
    @EnvironmentObject private var auth: AuthStore

    func body(content: Content) -> some View {
        content
            .overlay(alignment: .topTrailing) {
                if auth.isLoggedIn {
                    StaffShellNotificationBell()
                        .padding(.top, 8)
                        .padding(.trailing, 16)
                }
            }
    }
}

extension View {
    func staffShellNotificationOverlay() -> some View {
        modifier(StaffShellNotificationOverlay())
    }
}
