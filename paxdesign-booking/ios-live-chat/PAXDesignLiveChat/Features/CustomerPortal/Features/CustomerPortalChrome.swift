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
                Image(systemName: "person.crop.circle.fill")
                    .font(.system(size: size * 0.62))
                    .foregroundStyle(PAXTheme.accent)
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
                Label(String(localized: "My Workflow"), systemImage: "square.grid.2x2")
            }
            Button {
                navigation.openProjectsList()
            } label: {
                Label(String(localized: "Projects"), systemImage: "folder")
            }
            Button {
                navigation.openOrdersList()
            } label: {
                Label(String(localized: "Requests"), systemImage: "doc.text")
            }
            Button {
                navigation.openFiles()
            } label: {
                Label(String(localized: "Files & Invoices"), systemImage: "doc.on.doc")
            }
            Button {
                navigation.openNotifications()
            } label: {
                Label(String(localized: "Notifications"), systemImage: "bell")
            }
            Button {
                navigation.openNewsList()
            } label: {
                Label(String(localized: "News"), systemImage: "newspaper")
            }
            Button {
                navigation.openConversationsList()
            } label: {
                Label(String(localized: "Conversations"), systemImage: "bubble.left.and.bubble.right")
            }
        } label: {
            Image(systemName: "line.3.horizontal")
                .font(.system(size: 17, weight: .medium))
                .foregroundStyle(PAXTheme.textPrimary)
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
                            CustomerNavAvatarButton()
                            CustomerPortalQuickMenu()
                        }
                    }
                } else {
                    ToolbarItem(placement: .topBarTrailing) {
                        HStack(spacing: 8) {
                            PAXAppearanceQuickSwitch()
                            CustomerPortalQuickMenu()
                        }
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
