import SwiftUI
#if !SIDELOAD
import PhotosUI
#endif

struct GeneralSettingsView: View {
    @EnvironmentObject private var auth: AuthStore
    @EnvironmentObject private var push: PushService
    @EnvironmentObject private var settings: AppSettingsStore
    @Environment(\.dismiss) private var dismiss

    #if SIDELOAD
    @State private var showPhotoLibrary = false
    #else
    @State private var photoItem: PhotosPickerItem?
    #endif

    var body: some View {
        List {
            Section(L10n.SettingsProfile) {
                HStack(spacing: 16) {
                    ProfileAvatarView(size: 72)
                    VStack(alignment: .leading, spacing: 4) {
                        Text(auth.profile?.displayName ?? L10n.CommonAdministrator)
                            .font(.headline)
                        if let email = auth.profile?.email, !email.isEmpty {
                            Text(email)
                                .font(.subheadline)
                                .foregroundStyle(PAXTheme.textSecondary)
                        }
                    }
                }
                .padding(.vertical, 4)

                #if SIDELOAD
                Button {
                    showPhotoLibrary = true
                } label: {
                    Label { Text(L10n.SettingsChangePhoto) } icon: { PAXIcon("photo.circle") }
                }
                #else
                PhotosPicker(selection: $photoItem, matching: .images) {
                    Label { Text(L10n.SettingsChangePhoto) } icon: { PAXIcon("photo.circle") }
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
                            message: L10n.SettingsResetPhotoMessage,
                            confirmTitle: L10n.SettingsResetPhoto
                        ) {
                            settings.profileImageData = nil
                        }
                    }
                }
            }

            Section {
                Button(L10n.SettingsSignOut) {
                    PAXDelete.confirm(
                        title: L10n.SettingsSignOut,
                        message: L10n.SettingsSignOutMessage,
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
    }
}
