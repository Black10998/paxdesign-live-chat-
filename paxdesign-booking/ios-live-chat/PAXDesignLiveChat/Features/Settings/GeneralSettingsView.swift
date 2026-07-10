import SwiftUI
#if !SIDELOAD
import PhotosUI
#endif

struct GeneralSettingsView: View {
    @EnvironmentObject private var auth: AuthStore
    @EnvironmentObject private var push: PushService
    @EnvironmentObject private var settings: AppSettingsStore
    @Environment(\.dismiss) private var dismiss

    @State private var appPasswordDraft = ""
    @State private var statusMessage: String?
    #if SIDELOAD
    @State private var showPhotoLibrary = false
    #else
    @State private var photoItem: PhotosPickerItem?
    #endif

    private var canManageSettings: Bool { auth.canManageSettings }

    var body: some View {
        List {
            Section(L10n.SettingsProfile) {
                HStack(spacing: 16) {
                    ProfileAvatarView(size: 72)
                    VStack(alignment: .leading, spacing: 4) {
                        Text(auth.profile?.displayName ?? L10n.CommonAdministrator)
                            .font(.headline)
                        Text(auth.profile?.displayEmail ?? PrivacyMask.email(auth.username, revealFull: false))
                            .font(.subheadline)
                            .foregroundStyle(PAXTheme.textSecondary)
                    }
                }
                .padding(.vertical, 4)

                #if SIDELOAD
                Button {
                    showPhotoLibrary = true
                } label: {
                    Label(L10n.SettingsChangePhoto, systemImage: "photo.circle")
                }
                #else
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
                #endif

                if settings.profileImageData != nil {
                    Button(L10n.SettingsResetPhoto) {
                        PAXDelete.confirm(
                            message: "Das Profilbild wird entfernt.",
                            confirmTitle: L10n.SettingsResetPhoto
                        ) {
                            settings.profileImageData = nil
                        }
                    }
                }
            }

            if canManageSettings {
                Section {
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
                } header: {
                    Text(L10n.SettingsAccount)
                } footer: {
                    Text(L10n.SettingsCredentialsFooter)
                }

                Section {
                    Button(L10n.SettingsSignOut) {
                        PAXDelete.confirm(
                            message: "Sie werden von diesem Gerät abgemeldet.",
                            confirmTitle: L10n.SettingsSignOut
                        ) {
                            Task {
                                await push.unregisterTokenFromBackend(auth: auth)
                                auth.logout()
                                dismiss()
                            }
                        }
                    }
                }
            }
        }
        .listStyle(.insetGrouped)
        .scrollContentBackground(.hidden)
        .paxScreenBackground()
        .navigationTitle(L10n.SettingsSectionGeneral)
        .navigationBarTitleDisplayMode(.inline)
        #if SIDELOAD
        .sheet(isPresented: $showPhotoLibrary) {
            LibraryImagePicker { image in
                settings.profileImageData = image.jpegData(compressionQuality: 0.88)
                PAXHaptics.light()
            }
        }
        #endif
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
