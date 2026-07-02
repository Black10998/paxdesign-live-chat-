import PhotosUI
import SwiftUI
import UIKit

struct SettingsView: View {
    @EnvironmentObject private var auth: AuthStore
    @EnvironmentObject private var push: PushService
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
        .navigationTitle("Profil & Einstellungen")
        .navigationBarTitleDisplayMode(.inline)
        .onAppear {
            appPasswordDraft = auth.appPassword
        }
    }

    private var settingsRestrictedView: some View {
        List {
            profileSection
            Section {
                Text("Sie haben keine Berechtigung, App-Einstellungen zu ändern. Wenden Sie sich an den Hauptadministrator.")
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
            profileSection
            accountSection
            notificationSection
            soundSection
            aboutSection
        }
        .listStyle(.insetGrouped)
        .scrollContentBackground(.hidden)
    }

    private var profileSection: some View {
        Section {
            HStack(spacing: 16) {
                ProfileAvatarView(size: 72)

                VStack(alignment: .leading, spacing: 4) {
                    Text(auth.profile?.name ?? "Administrator")
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
                Label("Profilbild ändern", systemImage: "photo.circle")
            }
            .onChange(of: photoItem) { item in
                Task {
                    if let data = try? await item?.loadTransferable(type: Data.self) {
                        settings.profileImageData = data
                    }
                }
            }

            if settings.profileImageData != nil {
                Button("Profilbild zurücksetzen", role: .destructive) {
                    settings.profileImageData = nil
                }
            }
        } header: {
            Text("Profil")
        }
    }

    private var accountSection: some View {
        Section {
            LabeledContent("Website") {
                Text(auth.siteURLString)
                    .foregroundStyle(PAXTheme.textSecondary)
                    .multilineTextAlignment(.trailing)
            }

            SecureField("Application Password", text: $appPasswordDraft)
                .textInputAutocapitalization(.never)

            Button("Zugangsdaten speichern") {
                Task { await saveCredentials() }
            }

            if let statusMessage {
                Text(statusMessage)
                    .font(.footnote)
                    .foregroundStyle(PAXTheme.textSecondary)
            }

            Button("Abmelden", role: .destructive) {
                Task {
                    await push.unregisterTokenFromBackend(auth: auth)
                    auth.logout()
                    dismiss()
                }
            }
        } header: {
            Text("Konto")
        } footer: {
            Text("Das Application Password wird in WordPress unter Benutzer → Profil erstellt. Leerzeichen werden automatisch entfernt.")
        }
    }

    private var notificationSection: some View {
        Section("Benachrichtigungen") {
            Toggle("Push-Benachrichtigungen", isOn: $settings.notificationsEnabled)
            Toggle("Neue Nachrichten", isOn: $settings.messageSoundEnabled)
        }
    }

    private var soundSection: some View {
        Section {
            Toggle("Klingelton bei Live-Anfrage", isOn: $settings.incomingCallSoundEnabled)
            Toggle("Sendeton", isOn: $settings.sendSoundEnabled)
            Toggle("Tippgeräusch beim Schreiben", isOn: $settings.typingSoundEnabled)
            VStack(alignment: .leading, spacing: 8) {
                Text("Lautstärke")
                    .font(.subheadline)
                Slider(value: $settings.ringtoneVolume, in: 0.2...1.0)
            }
            Button("Klingelton testen") {
                IncomingCallRingtone.shared.startRinging()
                DispatchQueue.main.asyncAfter(deadline: .now() + 2.5) {
                    IncomingCallRingtone.shared.stopRinging()
                }
            }
        } header: {
            Text("Ton")
        }
    }

    private var aboutSection: some View {
        Section("App") {
            LabeledContent("Version", value: PAXAppInfo.fullVersion)
            LabeledContent("Plugin", value: auth.profile?.pluginVer ?? "—")
        }
    }

    private func saveCredentials() async {
        auth.appPassword = appPasswordDraft
        do {
            try await auth.login()
            statusMessage = "Zugangsdaten gespeichert."
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
