import SwiftUI

struct ActivityLogView: View {
    @EnvironmentObject private var auth: AuthStore
    @ObservedObject private var log = ActivityLogService.shared
    @State private var filterModule: String?
    @State private var isInitialLoading = true

    private var filteredEntries: [ActivityLogEntry] {
        guard let filterModule else { return log.entries }
        return log.entries.filter { $0.module == filterModule }
    }

    var body: some View {
        List {
            if isInitialLoading {
                Section {
                    PAXScreenLoadingStack(status: L10n.ModuleActivityLog, rowCount: 4)
                }
            } else {
            Section {
                Picker(L10n.FilterAll, selection: $filterModule) {
                    Text(L10n.FilterAll).tag(String?.none)
                    ForEach(uniqueModules, id: \.self) { module in
                        Text(moduleTitle(module)).tag(Optional(module))
                    }
                }
            }

            if filteredEntries.isEmpty {
                Section {
                    Text(L10n.ActivityLogEmpty)
                        .foregroundStyle(PAXTheme.textSecondary)
                }
            } else {
                Section(L10n.ModuleActivityLog) {
                    ForEach(filteredEntries) { entry in
                        activityRow(entry)
                    }
                }
            }
            }
        }
        .listStyle(.insetGrouped)
        .scrollContentBackground(.hidden)
        .paxScreenBackground()
        .navigationTitle(L10n.ModuleActivityLog)
        .navigationBarTitleDisplayMode(.large)
        .toolbar {
            ToolbarItem(placement: .topBarTrailing) {
                NavigationLink { ModuleActivitySettingsView() } label: {
                    PAXIcon( "slider.horizontal.3")
                }
            }
            if !log.entries.isEmpty, auth.canManageUsers {
                ToolbarItem(placement: .topBarTrailing) {
                    Button(L10n.ActivityLogClear) {
                        PAXDelete.confirm(
                            message: L10n.ActivityLogClearConfirm,
                            confirmTitle: L10n.ActivityLogClear
                        ) {
                            Task {
                                await log.clear(auth: auth)
                                PAXHaptics.warning()
                            }
                        }
                    }
                }
            }
        }
        .paxPremiumRefreshable(status: L10n.ModuleActivityLog, rowCount: 4) {
            await PlatformSyncService.shared.sync(auth: auth)
        }
        .task {
            await PlatformSyncService.shared.sync(auth: auth)
            withAnimation(.easeOut(duration: 0.25)) {
                isInitialLoading = false
            }
        }
    }

    private var uniqueModules: [String] {
        Array(Set(log.entries.map(\.module))).sorted()
    }

    private func moduleTitle(_ raw: String) -> String {
        PlatformModule(rawValue: raw)?.title ?? raw.capitalized
    }

    private func activityRow(_ entry: ActivityLogEntry) -> some View {
        VStack(alignment: .leading, spacing: 4) {
            HStack {
                Text(entry.title).font(.subheadline.weight(.semibold))
                Spacer()
                Text(entry.timestamp, style: .time)
                    .font(.caption2)
                    .foregroundStyle(PAXTheme.textTertiary)
            }
            if !entry.detail.isEmpty {
                Text(entry.detail)
                    .font(.caption)
                    .foregroundStyle(PAXTheme.textSecondary)
            }
            Text(entry.category)
                .font(.caption2.weight(.bold))
                .foregroundStyle(severityColor(entry.severity))
        }
        .padding(.vertical, 2)
    }

    private func severityColor(_ severity: ActivityLogEntry.Severity) -> Color {
        switch severity {
        case .info: return PAXTheme.textTertiary
        case .success: return PAXTheme.success
        case .warning: return .orange
        case .action: return PAXTheme.accent
        }
    }
}
