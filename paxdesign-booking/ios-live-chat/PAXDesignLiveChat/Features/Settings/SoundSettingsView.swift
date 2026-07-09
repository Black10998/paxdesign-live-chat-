import SwiftUI

struct SoundSettingsView: View {
    @StateObject private var settings = AppSettingsStore.shared

    var body: some View {
        List {
            Section {
                Toggle(L10n.SettingsIncomingRingtone, isOn: $settings.incomingCallSoundEnabled)
                Toggle("Nachrichtentöne", isOn: $settings.messageSoundEnabled)
                Toggle(L10n.SettingsSendSound, isOn: $settings.sendSoundEnabled)
                Toggle(L10n.SettingsTypingSound, isOn: $settings.typingSoundEnabled)

                VStack(alignment: .leading, spacing: 8) {
                    Text(L10n.SettingsVolume)
                        .font(.subheadline)
                    Slider(value: $settings.ringtoneVolume, in: 0.2...1.0)
                        .accessibilityLabel(L10n.SettingsVolume)
                }

                soundTestButton("Live-Anfrage", tone: .liveRequest)
                soundTestButton("Kundennachricht", tone: .message)
                soundTestButton("KI-Hinweis", tone: .aiAlert)
                soundTestButton("Senden", tone: .send)
            } header: {
                Text(L10n.SettingsSound)
            } footer: {
                Text(L10n.SettingsSoundDetailFooter)
            }
        }
        .listStyle(.insetGrouped)
        .scrollContentBackground(.hidden)
        .background(PAXBackground())
        .navigationTitle(L10n.SettingsSound)
        .navigationBarTitleDisplayMode(.inline)
    }

    private func soundTestButton(_ title: String, tone: PAXNotificationSound.Tone) -> some View {
        Button(title) {
            if tone == .liveRequest {
                IncomingCallRingtone.shared.startRinging()
                DispatchQueue.main.asyncAfter(deadline: .now() + 2.5) {
                    IncomingCallRingtone.shared.stopRinging()
                }
            } else {
                PAXNotificationSound.shared.play(tone, respectSettings: false)
            }
        }
    }
}
