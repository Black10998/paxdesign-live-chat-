import SwiftUI
import UserNotifications

struct NotificationSettingsView: View {
    @EnvironmentObject private var permissions: PermissionCoordinator
    @EnvironmentObject private var settings: AppSettingsStore
    @EnvironmentObject private var push: PushService
    @EnvironmentObject private var auth: AuthStore
    @State private var isRequestingPermission = false

    var body: some View {
        List {
            Section {
                HStack {
                    Text(L10n.SettingsNotificationsStatus)
                    Spacer()
                    Text(permissions.notificationStatusLabel)
                        .foregroundStyle(statusColor)
                }

                if permissions.canRequestNotificationPermission {
                    Button {
                        Task { await requestPermission() }
                    } label: {
                        HStack {
                            Text(L10n.SettingsNotificationsEnable)
                            Spacer()
                            if isRequestingPermission {
                                ProgressView()
                            }
                        }
                    }
                    .disabled(isRequestingPermission)
                }

                if permissions.shouldOpenSystemSettingsForNotifications {
                    Button(L10n.SettingsOpenIosSettings) {
                        permissions.openSystemSettings()
                    }
                }

                Toggle(L10n.SettingsPush, isOn: $settings.notificationsEnabled)
                    .onChange(of: settings.notificationsEnabled) { enabled in
                        if enabled {
                            Task {
                                await permissions.refreshStatuses()
                                if permissions.canRequestNotificationPermission {
                                    await requestPermission()
                                } else if permissions.notificationStatus == .authorized
                                            || permissions.notificationStatus == .provisional
                                            || permissions.notificationStatus == .ephemeral {
                                    _ = await PushService.shared.ensureDeviceToken()
                                    await PushService.shared.registerTokenWithBackend(auth: auth)
                                }
                            }
                        }
                    }
                Toggle(L10n.SettingsNewMessages, isOn: $settings.messageSoundEnabled)
            } header: {
                Text(L10n.SettingsNotifications)
            } footer: {
                Text(L10n.SettingsNotificationsFooter)
            }

            Section {
                NavigationLink {
                    PushDiagnosticsView()
                } label: {
                    Label { Text(L10n.PushDiagTitle) } icon: { PAXIcon("bell.badge") }
                }
            }
        }
        .listStyle(.insetGrouped)
        .scrollContentBackground(.hidden)
        .paxScreenBackground()
        .navigationTitle(L10n.SettingsSectionNotifications)
        .navigationBarTitleDisplayMode(.inline)
        .task {
            await permissions.refreshStatuses()
        }
        .onChange(of: permissions.notificationStatus) { _ in
            Task {
                if permissions.notificationStatus == .authorized
                    || permissions.notificationStatus == .provisional
                    || permissions.notificationStatus == .ephemeral {
                    _ = await PushService.shared.ensureDeviceToken()
                    await PushService.shared.registerTokenWithBackend(auth: auth)
                }
            }
        }
    }

    private var statusColor: Color {
        switch permissions.notificationStatus {
        case .authorized, .provisional, .ephemeral:
            return PAXTheme.success
        case .denied:
            return PAXTheme.danger
        case .notDetermined:
            return PAXTheme.textSecondary
        @unknown default:
            return PAXTheme.textSecondary
        }
    }

    private func requestPermission() async {
        isRequestingPermission = true
        defer { isRequestingPermission = false }
        let granted = await permissions.requestNotifications(push: push)
        if granted {
            await push.registerTokenWithBackend(auth: auth)
        }
    }
}
