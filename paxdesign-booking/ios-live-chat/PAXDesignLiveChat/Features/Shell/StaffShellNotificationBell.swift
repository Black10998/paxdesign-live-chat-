import SwiftUI

struct StaffShellNotificationBell: View {
    @EnvironmentObject private var auth: AuthStore
    @EnvironmentObject private var coordinator: ChatCoordinator
    @ObservedObject private var orders = StaffOrdersCoordinator.shared
    @State private var showCenter = false

    private var totalUnread: Int {
        coordinator.unreadChatCount + coordinator.liveCount + orders.unreadCount
    }

    var body: some View {
        Button {
            showCenter = true
            PAXHaptics.light()
        } label: {
            ZStack(alignment: .topTrailing) {
                PAXIcon("bell")
                    .frame(width: 36, height: 36)
                if totalUnread > 0 {
                    Text(totalUnread > 99 ? "99+" : "\(totalUnread)")
                        .font(.system(size: 10, weight: .bold))
                        .foregroundStyle(.white)
                        .padding(.horizontal, totalUnread > 9 ? 5 : 4)
                        .padding(.vertical, 2)
                        .background(Color.red)
                        .clipShape(Capsule())
                        .offset(x: 8, y: -4)
                        .accessibilityLabel(L10n.NotificationsCenterTitle)
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
