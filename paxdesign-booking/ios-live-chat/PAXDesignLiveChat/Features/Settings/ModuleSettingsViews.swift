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
                    Label { Text(L10n.ModuleCalendar) } icon: { PAXIcon("calendar") }
                }
                NavigationLink { ModuleTasksSettingsView() } label: {
                    Label { Text(L10n.ModuleTasks) } icon: { PAXIcon("checklist") }
                }
                NavigationLink { ModuleFilesSettingsView() } label: {
                    Label { Text(L10n.ModuleFiles) } icon: { PAXIcon("folder.fill") }
                }
            }

            Section(L10n.ModuleCategoryInsights) {
                NavigationLink { ModuleReportsSettingsView() } label: {
                    Label { Text(L10n.ModuleReports) } icon: { PAXIcon("chart.xyaxis.line") }
                }
                NavigationLink { ModuleActivitySettingsView() } label: {
                    Label { Text(L10n.ModuleActivityLog) } icon: { PAXIcon("clock.arrow.circlepath") }
                }
                NavigationLink { ModuleDashboardSettingsView() } label: {
                    Label { Text(L10n.ModuleDashboard) } icon: { PAXIcon("house.fill") }
                }
            }

            Section(L10n.ModuleCategoryCommunication) {
                NavigationLink { ModuleNotificationsSettingsView() } label: {
                    Label { Text(L10n.PlatformNotifications) } icon: { PAXIcon("bell.badge") }
                }
            }

            Section(L10n.SettingsSectionAppearance) {
                NavigationLink { AccentColorSettingsView() } label: {
                    Label { Text(L10n.AccentColorTitle) } icon: { PAXIcon("paintpalette.fill") }
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
    @EnvironmentObject private var settings: AppSettingsStore

    private let accentColumns = [
        GridItem(.flexible(), spacing: 12),
        GridItem(.flexible(), spacing: 12),
        GridItem(.flexible(), spacing: 12)
    ]

    var body: some View {
        ScrollView {
            VStack(alignment: .leading, spacing: 16) {
                Text(L10n.AccentColorFooter)
                    .font(.caption)
                    .foregroundStyle(PAXTheme.textSecondary)

                LazyVGrid(columns: accentColumns, spacing: 12) {
                    ForEach(AccentColorPreset.allCases) { preset in
                        Button {
                            withAnimation(PAXTheme.spring) {
                                settings.accentColorPreset = preset
                                PAXHaptics.light()
                            }
                        } label: {
                            AccentColorCard(
                                preset: preset,
                                isSelected: settings.accentColorPreset == preset,
                                previewAccent: previewAccent(for: preset)
                            )
                        }
                        .buttonStyle(.plain)
                    }
                }
            }
            .padding(.horizontal, 16)
            .padding(.vertical, 12)
        }
        .paxScreenBackground()
        .navigationTitle(L10n.AccentColorTitle)
        .navigationBarTitleDisplayMode(.inline)
    }

    private func previewAccent(for preset: AccentColorPreset) -> Color {
        if let color = preset.color {
            return color
        }
        return settings.basePalette.accent
    }
}

private struct AccentColorCard: View {
    let preset: AccentColorPreset
    let isSelected: Bool
    let previewAccent: Color

    var body: some View {
        VStack(spacing: 10) {
            ZStack {
                Circle()
                    .stroke(
                        AngularGradient(
                            colors: [previewAccent, previewAccent.opacity(0.55), previewAccent],
                            center: .center
                        ),
                        lineWidth: isSelected ? 3.5 : 2
                    )
                    .frame(width: 52, height: 52)

                if let color = preset.color {
                    Circle()
                        .fill(color)
                        .frame(width: 34, height: 34)
                } else {
                    Circle()
                        .fill(Color(.tertiarySystemFill))
                        .frame(width: 34, height: 34)
                        .overlay {
                            PAXIcon("paintbrush.pointed.fill", size: .row, emphasis: .secondary)
                        }
                }

                if isSelected {
                    PAXIcon("checkmark", size: .micro, emphasis: .onFill)
                        .offset(x: 18, y: -18)
                        .background(
                            Circle()
                                .fill(previewAccent)
                                .frame(width: 16, height: 16)
                                .offset(x: 18, y: -18)
                        )
                }
            }

            Text(preset.title)
                .font(.caption.weight(.semibold))
                .foregroundStyle(PAXTheme.textPrimary)
                .lineLimit(2)
                .minimumScaleFactor(0.85)
                .multilineTextAlignment(.center)
        }
        .padding(.vertical, 12)
        .padding(.horizontal, 8)
        .frame(maxWidth: .infinity, minHeight: 118)
        .background(
            RoundedRectangle(cornerRadius: 16, style: .continuous)
                .fill(Color(.secondarySystemGroupedBackground).opacity(0.72))
                .overlay(
                    RoundedRectangle(cornerRadius: 16, style: .continuous)
                        .stroke(isSelected ? previewAccent.opacity(0.55) : PAXTheme.border.opacity(0.22), lineWidth: isSelected ? 1.5 : 0.5)
                )
        )
        .accessibilityAddTraits(isSelected ? .isSelected : [])
    }
}

struct ModuleCalendarSettingsView: View {
    @EnvironmentObject private var auth: AuthStore
    @ObservedObject private var settings = PlatformModuleSettingsStore.shared

    var body: some View {
        moduleSettingsList(title: L10n.ModuleCalendar) {
            Toggle(L10n.ModuleSettingWeekStartMonday, isOn: $settings.calendarWeekStartMonday)
                .onChange(of: settings.calendarWeekStartMonday) { _ in settings.scheduleSave(auth: auth) }
            Toggle(L10n.ModuleSettingShowWeekNumbers, isOn: $settings.calendarShowWeekNumbers)
                .onChange(of: settings.calendarShowWeekNumbers) { _ in settings.scheduleSave(auth: auth) }
        }
    }
}

struct ModuleTasksSettingsView: View {
    @EnvironmentObject private var auth: AuthStore
    @ObservedObject private var settings = PlatformModuleSettingsStore.shared

    var body: some View {
        moduleSettingsList(title: L10n.ModuleTasks) {
            Toggle(L10n.ModuleSettingSortDueDate, isOn: $settings.tasksSortByDueDate)
                .onChange(of: settings.tasksSortByDueDate) { _ in settings.scheduleSave(auth: auth) }
            Toggle(L10n.ModuleSettingShowCompleted, isOn: $settings.tasksShowCompleted)
                .onChange(of: settings.tasksShowCompleted) { _ in settings.scheduleSave(auth: auth) }
        }
    }
}

struct ModuleFilesSettingsView: View {
    @EnvironmentObject private var auth: AuthStore
    @ObservedObject private var settings = PlatformModuleSettingsStore.shared

    var body: some View {
        moduleSettingsList(title: L10n.ModuleFiles) {
            Toggle(L10n.ModuleSettingGroupByCategory, isOn: $settings.filesGroupByCategory)
                .onChange(of: settings.filesGroupByCategory) { _ in settings.scheduleSave(auth: auth) }
        }
    }
}

struct ModuleReportsSettingsView: View {
    @EnvironmentObject private var auth: AuthStore
    @ObservedObject private var settings = PlatformModuleSettingsStore.shared

    var body: some View {
        moduleSettingsList(title: L10n.ModuleReports) {
            Toggle(L10n.ModuleSettingIncludeClosed, isOn: $settings.reportsIncludeClosed)
                .onChange(of: settings.reportsIncludeClosed) { _ in settings.scheduleSave(auth: auth) }
        }
    }
}

struct ModuleActivitySettingsView: View {
    @EnvironmentObject private var auth: AuthStore
    @ObservedObject private var settings = PlatformModuleSettingsStore.shared

    var body: some View {
        moduleSettingsList(title: L10n.ModuleActivityLog) {
            Toggle(L10n.ModuleSettingVerboseActivity, isOn: $settings.activityVerboseLogging)
                .onChange(of: settings.activityVerboseLogging) { _ in settings.scheduleSave(auth: auth) }
        }
    }
}

struct ModuleDashboardSettingsView: View {
    @EnvironmentObject private var auth: AuthStore
    @ObservedObject private var settings = PlatformModuleSettingsStore.shared

    var body: some View {
        moduleSettingsList(title: L10n.ModuleDashboard) {
            Toggle(L10n.ModuleSettingShowChart, isOn: $settings.dashboardShowChart)
                .onChange(of: settings.dashboardShowChart) { _ in settings.scheduleSave(auth: auth) }
            Toggle(L10n.ModuleSettingShowUpcoming, isOn: $settings.dashboardShowUpcoming)
                .onChange(of: settings.dashboardShowUpcoming) { _ in settings.scheduleSave(auth: auth) }
        }
    }
}

struct ModuleNotificationsSettingsView: View {
    @EnvironmentObject private var auth: AuthStore
    @ObservedObject private var settings = PlatformModuleSettingsStore.shared

    var body: some View {
        moduleSettingsList(title: L10n.PlatformNotifications) {
            Toggle(L10n.ModuleSettingInteractiveNotifications, isOn: $settings.notificationsInteractiveCards)
                .onChange(of: settings.notificationsInteractiveCards) { _ in settings.scheduleSave(auth: auth) }
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
