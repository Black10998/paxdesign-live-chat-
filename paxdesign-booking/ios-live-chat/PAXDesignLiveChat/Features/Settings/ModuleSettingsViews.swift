import SwiftUI

struct ModuleSettingsHubView: View {
    var body: some View {
        List {
            Section(L10n.ModuleSettingsIntro) {
                Text(L10n.ModuleSettingsIntroBody)
                    .font(.footnote)
                    .foregroundStyle(PAXTheme.textSecondary)
            }

            Section(L10n.ModuleCategoryWorkspace) {
                NavigationLink { ModuleCalendarSettingsView() } label: {
                    Label(L10n.ModuleCalendar, systemImage: "calendar")
                }
                NavigationLink { ModuleTasksSettingsView() } label: {
                    Label(L10n.ModuleTasks, systemImage: "checklist")
                }
                NavigationLink { ModuleFilesSettingsView() } label: {
                    Label(L10n.ModuleFiles, systemImage: "folder.fill")
                }
            }

            Section(L10n.ModuleCategoryInsights) {
                NavigationLink { ModuleReportsSettingsView() } label: {
                    Label(L10n.ModuleReports, systemImage: "chart.xyaxis.line")
                }
                NavigationLink { ModuleActivitySettingsView() } label: {
                    Label(L10n.ModuleActivityLog, systemImage: "clock.arrow.circlepath")
                }
                NavigationLink { ModuleDashboardSettingsView() } label: {
                    Label(L10n.ModuleDashboard, systemImage: "house.fill")
                }
            }

            Section(L10n.ModuleCategoryCommunication) {
                NavigationLink { ModuleNotificationsSettingsView() } label: {
                    Label(L10n.PlatformNotifications, systemImage: "bell.badge")
                }
            }

            Section(L10n.SettingsSectionAppearance) {
                NavigationLink { AccentColorSettingsView() } label: {
                    Label(L10n.AccentColorTitle, systemImage: "paintpalette.fill")
                }
            }
        }
        .listStyle(.insetGrouped)
        .scrollContentBackground(.hidden)
        .paxScreenBackground()
        .navigationTitle(L10n.ModuleSettingsTitle)
        .navigationBarTitleDisplayMode(.large)
    }
}

struct AccentColorSettingsView: View {
    @StateObject private var settings = AppSettingsStore.shared

    var body: some View {
        List {
            Section(L10n.AccentColorTitle) {
                ForEach(AccentColorPreset.allCases) { preset in
                    Button {
                        settings.accentColorPreset = preset
                        PAXHaptics.light()
                    } label: {
                        HStack {
                            if let color = preset.color {
                                Circle().fill(color).frame(width: 22, height: 22)
                            } else {
                                Image(systemName: "paintbrush.pointed.fill")
                                    .foregroundStyle(PAXTheme.accent)
                            }
                            Text(preset.title)
                                .foregroundStyle(PAXTheme.textPrimary)
                            Spacer()
                            if settings.accentColorPreset == preset {
                                Image(systemName: "checkmark.circle.fill")
                                    .foregroundStyle(PAXTheme.accent)
                            }
                        }
                    }
                }
            }
            Section {
                Text(L10n.AccentColorFooter)
                    .font(.footnote)
                    .foregroundStyle(PAXTheme.textSecondary)
            }
        }
        .listStyle(.insetGrouped)
        .scrollContentBackground(.hidden)
        .paxScreenBackground()
        .navigationTitle(L10n.AccentColorTitle)
    }
}

struct ModuleCalendarSettingsView: View {
    @AppStorage("pax.module.calendar.weekStartMonday") private var weekStartMonday = true
    @AppStorage("pax.module.calendar.showWeekNumbers") private var showWeekNumbers = false

    var body: some View {
        moduleSettingsList(title: L10n.ModuleCalendar) {
            Toggle(L10n.ModuleSettingWeekStartMonday, isOn: $weekStartMonday)
            Toggle(L10n.ModuleSettingShowWeekNumbers, isOn: $showWeekNumbers)
        }
    }
}

struct ModuleTasksSettingsView: View {
    @AppStorage("pax.module.tasks.sortByDueDate") private var sortByDueDate = true
    @AppStorage("pax.module.tasks.showCompleted") private var showCompleted = true

    var body: some View {
        moduleSettingsList(title: L10n.ModuleTasks) {
            Toggle(L10n.ModuleSettingSortDueDate, isOn: $sortByDueDate)
            Toggle(L10n.ModuleSettingShowCompleted, isOn: $showCompleted)
        }
    }
}

struct ModuleFilesSettingsView: View {
    @AppStorage("pax.module.files.groupByCategory") private var groupByCategory = true

    var body: some View {
        moduleSettingsList(title: L10n.ModuleFiles) {
            Toggle(L10n.ModuleSettingGroupByCategory, isOn: $groupByCategory)
        }
    }
}

struct ModuleReportsSettingsView: View {
    @AppStorage("pax.module.reports.includeClosed") private var includeClosed = true

    var body: some View {
        moduleSettingsList(title: L10n.ModuleReports) {
            Toggle(L10n.ModuleSettingIncludeClosed, isOn: $includeClosed)
        }
    }
}

struct ModuleActivitySettingsView: View {
    @AppStorage("pax.module.activity.verbose") private var verboseLogging = false

    var body: some View {
        moduleSettingsList(title: L10n.ModuleActivityLog) {
            Toggle(L10n.ModuleSettingVerboseActivity, isOn: $verboseLogging)
        }
    }
}

struct ModuleDashboardSettingsView: View {
    @AppStorage("pax.module.dashboard.showChart") private var showChart = true
    @AppStorage("pax.module.dashboard.showUpcoming") private var showUpcoming = true

    var body: some View {
        moduleSettingsList(title: L10n.ModuleDashboard) {
            Toggle(L10n.ModuleSettingShowChart, isOn: $showChart)
            Toggle(L10n.ModuleSettingShowUpcoming, isOn: $showUpcoming)
        }
    }
}

struct ModuleNotificationsSettingsView: View {
    @AppStorage("pax.module.notifications.interactive") private var interactiveCards = true

    var body: some View {
        moduleSettingsList(title: L10n.PlatformNotifications) {
            Toggle(L10n.ModuleSettingInteractiveNotifications, isOn: $interactiveCards)
            NavigationLink { NotificationSettingsView() } label: {
                Text(L10n.SettingsSectionNotifications)
            }
        }
    }
}

@ViewBuilder
private func moduleSettingsList<Content: View>(title: String, @ViewBuilder content: () -> Content) -> some View {
    List {
        Section(title) {
            content()
        }
        Section {
            Text(L10n.ModuleSettingsFooter)
                .font(.footnote)
                .foregroundStyle(PAXTheme.textSecondary)
        }
    }
    .listStyle(.insetGrouped)
    .scrollContentBackground(.hidden)
    .paxScreenBackground()
    .navigationTitle(title)
    .navigationBarTitleDisplayMode(.inline)
}
