import SwiftUI

// MARK: - Top navigation chrome (avatar + quick menu)

struct CustomerNavAvatarButton: View {
    @EnvironmentObject private var auth: CustomerAuthStore
    @EnvironmentObject private var navigation: CustomerNavigationCoordinator

    var body: some View {
        Button {
            navigation.openAccountDestination(CustomerPortalDestination(kind: .profile))
            PAXHaptics.light()
        } label: {
            CustomerProfileAvatarView(
                urlString: auth.profile?.avatar_url,
                size: 32
            )
        }
        .buttonStyle(.plain)
        .accessibilityLabel(String(localized: "Account profile"))
    }
}

struct CustomerProfileAvatarView: View {
    let urlString: String?
    var size: CGFloat = 32

    var body: some View {
        Group {
            if let urlString, let url = URL(string: urlString) {
                AsyncImage(url: url) { phase in
                    switch phase {
                    case .success(let image):
                        image.resizable().scaledToFill()
                    default:
                        defaultAvatar
                    }
                }
            } else {
                defaultAvatar
            }
        }
        .frame(width: size, height: size)
        .clipShape(Circle())
        .overlay(
            Circle()
                .stroke(PAXTheme.border.opacity(0.35), lineWidth: 0.5)
        )
    }

    private var defaultAvatar: some View {
        Circle()
            .fill(PAXTheme.accentSoft)
            .overlay {
                PAXIcon("person.crop.circle.fill", size: size >= 48 ? .display : .card, tint: PAXTheme.accent)
            }
    }
}

struct CustomerPortalQuickMenu: View {
    @EnvironmentObject private var navigation: CustomerNavigationCoordinator

    var body: some View {
        Menu {
            Button {
                navigation.openAccountDestination(CustomerPortalDestination(kind: .dashboard))
            } label: {
                PAXLabel(String(localized: "My Workflow"), icon: "square.grid.2x2")
            }
            Button {
                navigation.openProjectsList()
            } label: {
                PAXLabel(String(localized: "Projects"), icon: "folder")
            }
            Button {
                navigation.openOrdersList()
            } label: {
                PAXLabel(String(localized: "Requests"), icon: "doc.text")
            }
            Button {
                navigation.openFiles()
            } label: {
                PAXLabel(String(localized: "Files & Invoices"), icon: "doc.on.doc")
            }
            Button {
                navigation.openNotifications()
            } label: {
                PAXLabel(String(localized: "Notifications"), icon: "bell")
            }
            Button {
                navigation.openNewsList()
            } label: {
                PAXLabel(String(localized: "News"), icon: "newspaper")
            }
            Button {
                navigation.openConversationsList()
            } label: {
                PAXLabel(String(localized: "Conversations"), icon: "bubble.left.and.bubble.right")
            }
        } label: {
            PAXIcon("line.3.horizontal", size: .card, emphasis: .primary)
                .frame(width: 36, height: 36)
                .background(.ultraThinMaterial, in: Circle())
                .overlay(
                    Circle()
                        .stroke(PAXTheme.border.opacity(0.25), lineWidth: 0.5)
                )
        }
        .accessibilityLabel(String(localized: "Quick menu"))
    }
}

private struct CustomerPortalToolbarModifier: ViewModifier {
    var showsAvatar: Bool = true

    func body(content: Content) -> some View {
        content
            .toolbar {
                if showsAvatar {
                    ToolbarItem(placement: .topBarTrailing) {
                        HStack(spacing: 8) {
                            PAXAppearanceQuickSwitch()
                            CustomerNotificationBellButton()
                            CustomerNavAvatarButton()
                            CustomerPortalQuickMenu()
                        }
                    }
                } else {
                    ToolbarItem(placement: .topBarTrailing) {
                        HStack(spacing: 8) {
                            PAXAppearanceQuickSwitch()
                            CustomerNotificationBellButton()
                            CustomerPortalQuickMenu()
                        }
                    }
                }
            }
    }
}

extension View {
    func customerPortalToolbar(showsAvatar: Bool = true) -> some View {
        modifier(CustomerPortalToolbarModifier(showsAvatar: showsAvatar))
    }
}
