import SwiftUI

struct SoundSettingsView: View {
    @EnvironmentObject private var settings: AppSettingsStore

    var body: some View {
        List {
            Section {
                Toggle(L10n.SettingsIncomingRingtone, isOn: $settings.incomingCallSoundEnabled)
                Toggle(L10n.SoundMessageTones, isOn: $settings.messageSoundEnabled)
                Toggle(L10n.SettingsSendSound, isOn: $settings.sendSoundEnabled)
                Toggle(L10n.SettingsTypingSound, isOn: $settings.typingSoundEnabled)

                tonePicker(L10n.SoundToneMessages, tone: .message)
                tonePicker(L10n.SoundToneLiveRequest, tone: .liveRequest)
                tonePicker(L10n.SoundToneAiAlert, tone: .aiAlert)
                tonePicker(L10n.SoundToneSend, tone: .send)
                tonePicker(L10n.SoundToneTyping, tone: .typing)

                VStack(alignment: .leading, spacing: 8) {
                    Text(L10n.SettingsVolume)
                        .font(.subheadline)
                    Slider(value: $settings.ringtoneVolume, in: 0.2...1.0)
                        .accessibilityLabel(L10n.SettingsVolume)
                }

                soundTestButton(L10n.SoundTestLiveRequest, tone: .liveRequest)
                soundTestButton(L10n.SoundTestMessage, tone: .message)
                soundTestButton(L10n.SoundTestAiAlert, tone: .aiAlert)
                soundTestButton(L10n.SoundTestSend, tone: .send)
                soundTestButton(L10n.SoundTestTyping, tone: .typing)
            } header: {
                Text(L10n.SettingsSound)
            } footer: {
                Text(L10n.SettingsSoundDetailFooter)
            }
        }
        .listStyle(.insetGrouped)
        .scrollContentBackground(.hidden)
        .paxScreenBackground()
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

    private func tonePicker(_ title: String, tone: PAXNotificationSound.Tone) -> some View {
        Picker(title, selection: Binding(
            get: { PAXNotificationSound.shared.selectedStyle(for: tone) },
            set: { newValue in
                PAXNotificationSound.shared.setSelectedStyle(newValue, for: tone)
            }
        )) {
            ForEach(PAXNotificationSound.shared.options(for: tone)) { option in
                Text(option.title).tag(option.id)
            }
        }
    }
}
