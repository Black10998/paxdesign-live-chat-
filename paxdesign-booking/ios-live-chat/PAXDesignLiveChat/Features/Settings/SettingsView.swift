import PhotosUI
import SwiftUI
import UIKit

struct SettingsView: View {
    @EnvironmentObject private var auth: AuthStore
    @EnvironmentObject private var push: PushService
    @EnvironmentObject private var permissions: PermissionCoordinator
    @StateObject private var settings = AppSettingsStore.shared
    @Environment(\.dismiss) private var dismiss

    @State private var appPasswordDraft = ""
    @State private var statusMessage: String?
    @State private var photoItem: PhotosPickerItem?

    private var canManageSettings: Bool { auth.canManageSettings }

    var body: some View {
        Group {
            if canManageSettings {
                settingsContent
            } else {
                settingsRestrictedView
            }
        }
        .background(PAXBackground())
        .navigationTitle(L10n.SettingsTitle)
        .navigationBarTitleDisplayMode(.inline)
        .onAppear {
            appPasswordDraft = auth.appPassword
        }
    }

    private var settingsRestrictedView: some View {
        List {
            appearanceSection
            profileSection
            Section {
                Text(L10n.SettingsNoPermission)
                    .font(.footnote)
                    .foregroundStyle(PAXTheme.textSecondary)
            }
            aboutSection
        }
        .listStyle(.insetGrouped)
        .scrollContentBackground(.hidden)
    }

    private var settingsContent: some View {
        List {
            appearanceSection
            profileSection
            accountSection
            securitySection
            notificationSection
            soundSection
            aboutSection
        }
        .listStyle(.insetGrouped)
        .scrollContentBackground(.hidden)
    }

    private var appearanceSection: some View {
        Section {
            Picker(L10n.AppearanceTitle, selection: $settings.appearanceMode) {
                ForEach(AppSettingsStore.AppearanceMode.allCases) { mode in
                    Text(mode.title).tag(mode)
                }
            }
            .pickerStyle(.inline)
        } footer: {
            Text(L10n.AppearanceFooter)
        }
    }

    private var profileSection: some View {
        Section {
            HStack(spacing: 16) {
                ProfileAvatarView(size: 72)

                VStack(alignment: .leading, spacing: 4) {
                    Text(auth.profile?.name ?? L10n.CommonAdministrator)
                        .font(.headline)
                    Text(auth.profile?.displayEmail ?? PrivacyMask.email(auth.username, revealFull: false))
                        .font(.subheadline)
                        .foregroundStyle(PAXTheme.textSecondary)
                    if let username = auth.profile?.username, !username.isEmpty {
                        Text("@\(username)")
                            .font(.caption)
                            .foregroundStyle(PAXTheme.textTertiary)
                    }
                }
            }
            .padding(.vertical, 6)

            PhotosPicker(selection: $photoItem, matching: .images) {
                Label(L10n.SettingsChangePhoto, systemImage: "photo.circle")
            }
            .onChange(of: photoItem) { item in
                Task {
                    if let data = try? await item?.loadTransferable(type: Data.self) {
                        settings.profileImageData = data
                    }
                }
            }

            if settings.profileImageData != nil {
                Button(L10n.SettingsResetPhoto, role: .destructive) {
                    settings.profileImageData = nil
                }
            }
        } header: {
            Text(L10n.SettingsProfile)
        }
    }

    private var accountSection: some View {
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

            Button(L10n.SettingsSignOut, role: .destructive) {
                Task {
                    await push.unregisterTokenFromBackend(auth: auth)
                    auth.logout()
                    dismiss()
                }
            }
        } header: {
            Text(L10n.SettingsAccount)
        } footer: {
            Text(L10n.SettingsCredentialsFooter)
        }
    }

    private var securitySection: some View {
        Section {
            NavigationLink {
                AppLockSettingsView()
            } label: {
                Label(L10n.SettingsAppLock, systemImage: "lock.shield")
            }
        } header: {
            Text(L10n.SettingsSecurity)
        } footer: {
            Text(L10n.SettingsAppLockFooter)
        }
    }

    private var notificationSection: some View {
        Section {
            Toggle(L10n.SettingsPush, isOn: $settings.notificationsEnabled)
                .onChange(of: settings.notificationsEnabled) { enabled in
                    if enabled {
                        Task {
                            await permissions.refreshStatuses()
                            if permissions.notificationStatus == .notDetermined {
                                _ = await permissions.requestNotifications(push: push)
                            }
                        }
                    }
                }
            Toggle(L10n.SettingsNewMessages, isOn: $settings.messageSoundEnabled)

            if permissions.notificationStatus == .denied {
                Button(L10n.SettingsOpenIosSettings) {
                    permissions.openSystemSettings()
                }
                .font(.footnote)
            }
        } header: {
            Text(L10n.SettingsNotifications)
        } footer: {
            Text(L10n.SettingsNotificationsFooter)
        }
    }

    private var soundSection: some View {
        Section {
            Toggle(L10n.SettingsIncomingRingtone, isOn: $settings.incomingCallSoundEnabled)
            Toggle(L10n.SettingsSendSound, isOn: $settings.sendSoundEnabled)
            Toggle(L10n.SettingsTypingSound, isOn: $settings.typingSoundEnabled)
            VStack(alignment: .leading, spacing: 8) {
                Text(L10n.SettingsVolume)
                    .font(.subheadline)
                Slider(value: $settings.ringtoneVolume, in: 0.2...1.0)
            }
            Button(L10n.SettingsTestRingtone) {
                IncomingCallRingtone.shared.startRinging()
                DispatchQueue.main.asyncAfter(deadline: .now() + 2.5) {
                    IncomingCallRingtone.shared.stopRinging()
                }
            }
        } header: {
            Text(L10n.SettingsSound)
        }
    }

    private var aboutSection: some View {
        Section(L10n.SettingsAppSection) {
            LabeledContent(L10n.CommonVersion, value: PAXAppInfo.fullVersion)
            LabeledContent(L10n.CommonPlugin, value: auth.profile?.pluginVer ?? "—")
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

struct ProfileAvatarView: View {
    @EnvironmentObject private var auth: AuthStore
    @StateObject private var settings = AppSettingsStore.shared
    var size: CGFloat = 40

    var body: some View {
        Group {
            if let data = settings.profileImageData, let uiImage = UIImage(data: data) {
                Image(uiImage: uiImage)
                    .resizable()
                    .scaledToFill()
            } else if let urlString = auth.profile?.avatarUrl, let url = URL(string: urlString) {
                AsyncImage(url: url) { phase in
                    switch phase {
                    case .success(let image):
                        image.resizable().scaledToFill()
                    default:
                        fallback
                    }
                }
            } else {
                fallback
            }
        }
        .frame(width: size, height: size)
        .clipShape(Circle())
        .overlay(Circle().stroke(PAXTheme.border, lineWidth: 1))
    }

    private var fallback: some View {
        PAXAvatar(name: auth.profile?.name ?? "PAX", size: size)
    }
}
