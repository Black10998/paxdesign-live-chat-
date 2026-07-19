import SwiftUI

/// Pre-permission education before the native iOS notification prompt.
/// Notification permission is optional — both actions must always dismiss this screen.
struct CustomerNotificationPermissionSheet: View {
    var onEnable: () -> Void
    var onSkip: () -> Void

    @State private var isRequesting = false

    var body: some View {
        NavigationStack {
            ScrollView {
                VStack(alignment: .leading, spacing: 20) {
                    Text(String(localized: "Stay updated"))
                        .font(.title2.weight(.bold))
                    Text(String(localized: "Notifications help you respond quickly when our team replies, when projects move forward, or when files and orders change."))
                        .foregroundStyle(PAXTheme.textSecondary)
                        .fixedSize(horizontal: false, vertical: true)

                    VStack(alignment: .leading, spacing: 14) {
                        permissionRow(String(localized: "Chat replies"), icon: "message.fill")
                        permissionRow(String(localized: "Project updates"), icon: "folder.fill")
                        permissionRow(String(localized: "Order updates"), icon: "doc.text.fill")
                        permissionRow(String(localized: "File updates"), icon: "paperclip")
                        permissionRow(String(localized: "Service updates"), icon: "sparkles")
                    }

                    VStack(spacing: 12) {
                        Button {
                            guard !isRequesting else { return }
                            isRequesting = true
                            onEnable()
                            isRequesting = false
                        } label: {
                            HStack(spacing: 8) {
                                if isRequesting {
                                    ProgressView()
                                        .controlSize(.small)
                                        .tint(.white)
                                }
                                Text(String(localized: "Enable notifications"))
                                    .fontWeight(.semibold)
                            }
                            .frame(maxWidth: .infinity)
                            .padding(.vertical, 14)
                            .contentShape(Rectangle())
                        }
                        .buttonStyle(CustomerPrimaryButtonStyleModifier(style: .filled))
                        .disabled(isRequesting)

                        Button {
                            onSkip()
                        } label: {
                            Text(String(localized: "Not now"))
                                .font(.subheadline.weight(.medium))
                                .frame(maxWidth: .infinity)
                                .padding(.vertical, 12)
                                .contentShape(Rectangle())
                        }
                        .buttonStyle(.plain)
                        .foregroundStyle(PAXTheme.textSecondary)
                    }
                    .padding(.top, 8)
                }
                .padding(24)
            }
            .navigationTitle(String(localized: "Notifications"))
            .navigationBarTitleDisplayMode(.inline)
        }
        .interactiveDismissDisabled(false)
    }

    private func permissionRow(_ title: String, icon: String) -> some View {
        HStack(spacing: 12) {
            Image(systemName: icon)
                .foregroundStyle(PAXTheme.accent)
                .frame(width: 28)
            Text(title)
                .font(.body)
        }
    }
}
