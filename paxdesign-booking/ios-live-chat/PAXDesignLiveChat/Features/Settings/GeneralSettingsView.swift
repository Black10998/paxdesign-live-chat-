import PhotosUI
import SwiftUI

struct GeneralSettingsView: View {
    @EnvironmentObject private var auth: AuthStore
    @EnvironmentObject private var push: PushService
    @StateObject private var settings = AppSettingsStore.shared
    @Environment(\.dismiss) private var dismiss

    @State private var appPasswordDraft = ""
    @State private var statusMessage: String?
    @State private var photoItem: PhotosPickerItem?

    private var canManageSettings: Bool { auth.canManageSettings }

    var body: some View {
        List {
            Section(L10n.SettingsProfile) {
                HStack(spacing: 16) {
                    ProfileAvatarView(size: 72)
                    VStack(alignment: .leading, spacing: 4) {
                        Text(auth.profile?.name ?? L10n.CommonAdministrator)
                            .font(.headline)
                        Text(auth.profile?.displayEmail ?? PrivacyMask.email(auth.username, revealFull: false))
                            .font(.subheadline)
                            .foregroundStyle(PAXTheme.textSecondary)
                    }
                }
                .padding(.vertical, 4)

                PhotosPicker(selection: $photoItem, matching: .images) {
                    Label(L10n.SettingsChangePhoto, systemImage: "photo.circle")
                }
                .onChange(of: photoItem) { item in
                    Task {
                        if let data = try? await item?.loadTransferable(type: Data.self) {
                            settings.profileImageData = data
                            PAXHaptics.light()
                        }
                    }
                }

                if settings.profileImageData != nil {
                    Button(L10n.SettingsResetPhoto, role: .destructive) {
                        settings.profileImageData = nil
                    }
                }
            }

            if canManageSettings {
                Section(L10n.SettingsAccount) {
                    LabeledContent(L10n.CommonWebsite) {
                        Text(auth.siteURLString)
                            .foregroundStyle(PAXTheme.textSecondary)
                            .multilineTextAlignment(.trailing)
                    }

                    SecureField(L10n.LoginAppPassword, text: $appPasswordDraft)
                        .textInputAutocapitalization(.never)

                    Button(L10n.SettingsSaveCredentials) {
                        Task { await saveCredentials() }
                    }

                    if let statusMessage {
                        Text(statusMessage)
                            .font(.footnote)
                            .foregroundStyle(PAXTheme.textSecondary)
                    }
                } footer: {
                    Text(L10n.SettingsCredentialsFooter)
                }

                Section {
                    Button(L10n.SettingsSignOut, role: .destructive) {
                        Task {
                            await push.unregisterTokenFromBackend(auth: auth)
                            auth.logout()
                            dismiss()
                        }
                    }
                }
            }
        }
        .listStyle(.insetGrouped)
        .scrollContentBackground(.hidden)
        .background(PAXBackground())
        .navigationTitle(L10n.SettingsSectionGeneral)
        .navigationBarTitleDisplayMode(.inline)
        .onAppear {
            appPasswordDraft = auth.appPassword
        }
    }

    private func saveCredentials() async {
        auth.appPassword = appPasswordDraft
        do {
            try await auth.login()
            statusMessage = L10n.SettingsCredentialsSaved
            PAXHaptics.success()
        } catch {
            statusMessage = error.localizedDescription
            PAXHaptics.warning()
        }
    }
}
