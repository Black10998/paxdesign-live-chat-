import SwiftUI

/// Pre-permission education before the native iOS notification prompt.
struct CustomerNotificationPermissionSheet: View {
    @Environment(\.dismiss) private var dismiss
    var onContinue: () -> Void

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

                    Button(String(localized: "Enable notifications")) {
                        dismiss()
                        onContinue()
                    }
                    .buttonStyle(CustomerPrimaryButtonStyleModifier(style: .filled))
                    .padding(.top, 8)

                    Button(String(localized: "Not now")) { dismiss() }
                        .frame(maxWidth: .infinity)
                }
                .padding(24)
            }
            .navigationTitle(String(localized: "Notifications"))
            .navigationBarTitleDisplayMode(.inline)
        }
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
