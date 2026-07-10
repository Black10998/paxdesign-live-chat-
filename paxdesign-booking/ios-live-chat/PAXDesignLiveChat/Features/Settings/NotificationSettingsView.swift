import SwiftUI

struct NotificationSettingsView: View {
    @EnvironmentObject private var permissions: PermissionCoordinator
    @EnvironmentObject private var settings: AppSettingsStore

    var body: some View {
        List {
            Section {
                Toggle(L10n.SettingsPush, isOn: $settings.notificationsEnabled)
                    .onChange(of: settings.notificationsEnabled) { enabled in
                        if enabled {
                            Task {
                                await permissions.refreshStatuses()
                                if permissions.notificationStatus == .notDetermined {
                                    _ = await permissions.requestNotifications(push: PushService.shared)
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
        .listStyle(.insetGrouped)
        .scrollContentBackground(.hidden)
        .paxScreenBackground()
        .navigationTitle(L10n.SettingsSectionNotifications)
        .navigationBarTitleDisplayMode(.inline)
    }
}
