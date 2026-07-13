import SwiftUI

struct DeviceManagementView: View {
    @EnvironmentObject private var auth: AuthStore
    @State private var devices: [DeviceRecord] = []
    @State private var isLoading = true
    @State private var errorMessage: String?
    @State private var selectedEmployeeId: Int?
    @State private var confirmApprove: DeviceRecord?
    @State private var liveRefreshTask: Task<Void, Never>?
    private let loadGuard = RequestInFlightGuard()

    private var canManage: Bool { auth.canManageUsers || auth.canManageTeamPermissions }

    var body: some View {
        List {
            if let errorMessage {
                Section {
                    Text(errorMessage)
                        .font(.caption)
                        .foregroundStyle(PAXTheme.danger)
                }
            }

            if isLoading {
                Section {
                    PAXScreenLoadingStack(status: L10n.DeviceLoading, rowCount: 3, preset: .deviceCards)
                }
            } else if devices.isEmpty {
                Section {
                    VStack(spacing: 10) {
                        PAXIcon("iphone.slash", emphasis: .tertiary)
                        Text(L10n.DeviceNoneTitle)
                            .font(.headline)
                        Text(L10n.DeviceNoneBody)
                            .font(.subheadline)
                            .foregroundStyle(PAXTheme.textSecondary)
                            .multilineTextAlignment(.center)
                    }
                    .frame(maxWidth: .infinity)
                    .padding(.vertical, 24)
                }
            } else {
                let grouped = Dictionary(grouping: devices, by: \.userId)
                ForEach(grouped.keys.sorted(), id: \.self) { userId in
                    if let employeeDevices = grouped[userId] {
                        Section(employeeDevices.first?.employeeName ?? L10n.DeviceDefaultEmployee) {
                            if canManage {
                                Text(employeeDevices.first?.employeeEmail ?? "")
                                    .font(.caption)
                                    .foregroundStyle(PAXTheme.textTertiary)
                            }
                            ForEach(employeeDevices) { device in
                                deviceRow(device)
                            }
                        }
                    }
                }
            }
        }
        .listStyle(.insetGrouped)
        .scrollContentBackground(.hidden)
        .paxScreenBackground()
        .navigationTitle(L10n.PlatformDevices)
        .navigationBarTitleDisplayMode(.inline)
        .paxPremiumRefreshable(status: L10n.DeviceLoading, rowCount: 3) { await loadDevices() }
        .task { startRealtimeRefresh() }
        .onDisappear { stopRealtimeRefresh() }
        .confirmationDialog(
            L10n.DeviceConfirmTitle,
            isPresented: Binding(
                get: { confirmApprove != nil },
                set: { if !$0 { confirmApprove = nil } }
            ),
            titleVisibility: .visible
        ) {
            if let device = confirmApprove {
                Button(L10n.DeviceActionApprove) {
                    Task { await approve(device) }
                }
            }
            Button(L10n.CommonCancel, role: .cancel) { confirmApprove = nil }
        } message: {
            if let device = confirmApprove {
                Text(L10n.DeviceConfirmApproveMessage(device.deviceName))
            }
        }
    }

    private func startRealtimeRefresh() {
        liveRefreshTask?.cancel()
        liveRefreshTask = Task {
            var intervalNs: UInt64 = 30_000_000_000
            while !Task.isCancelled {
                if NetworkCircuitBreaker.shared.isOpen {
                    intervalNs = 60_000_000_000
                    try? await Task.sleep(nanoseconds: intervalNs)
                    continue
                }
                let hadError = await loadDevices()
                intervalNs = hadError ? 60_000_000_000 : 30_000_000_000
                try? await Task.sleep(nanoseconds: intervalNs)
            }
        }
    }

    private func stopRealtimeRefresh() {
        liveRefreshTask?.cancel()
        liveRefreshTask = nil
    }

    private func deviceRow(_ device: DeviceRecord) -> some View {
        VStack(alignment: .leading, spacing: 8) {
            HStack(spacing: 12) {
                PAXDeviceGlyph(machine: device.deviceModel, size: 30)
                VStack(alignment: .leading, spacing: 2) {
                    Text(device.deviceName)
                        .font(.subheadline.weight(.semibold))
                    Text(PAXDeviceModelMapper.friendlyName(for: device.deviceModel))
                        .font(.caption)
                        .foregroundStyle(PAXTheme.textSecondary)
                }
                Spacer()
                statusBadge(device)
            }

            HStack(spacing: 6) {
                if device.isCurrent {
                    chip(L10n.DeviceChipCurrent, color: .blue)
                }
                chip(device.online ? L10n.CommonOnline : L10n.DeviceChipOffline, color: device.online ? .green : .gray)
                chip(device.approved && !device.revoked ? L10n.DeviceChipApproved : L10n.DeviceChipNotApproved, color: device.approved && !device.revoked ? .mint : .orange)
                chip(device.pushRegistered ? L10n.DeviceChipPushOn : L10n.DeviceChipPushOff, color: device.pushRegistered ? .green : .orange)
                chip(device.pushEnvironment == "sandbox" ? L10n.DeviceChipSandbox : L10n.DeviceChipProduction, color: device.pushEnvironment == "sandbox" ? .orange : .blue)
            }

            if !device.deviceModel.isEmpty {
                metaRow(L10n.DeviceMetaModel, PAXDeviceModelMapper.friendlyName(for: device.deviceModel))
            }
            metaRow(L10n.DeviceMetaSystem, device.osVersion)
            metaRow(L10n.DeviceMetaApp, device.appVersion)
            metaRow(L10n.DeviceMetaFirstLogin, formatTimestamp(device.firstLoginAt))
            metaRow(L10n.DeviceMetaLastActive, formatTimestamp(device.lastActiveAt))
            if !device.ipAddress.isEmpty {
                metaRow(L10n.DeviceMetaIp, device.ipAddress)
            }
            if !device.location.isEmpty {
                metaRow(L10n.DeviceMetaLocation, device.location)
            }
            if !device.deviceToken.isEmpty {
                metaRow(L10n.DeviceMetaToken, device.deviceToken)
            }

            if canManage {
                HStack(spacing: 10) {
                    if device.revoked || !device.approved {
                        Button {
                            confirmApprove = device
                        } label: {
                            Label { Text(L10n.DeviceActionApprove) } icon: { PAXIcon("checkmark.shield") }
                                .font(.caption.weight(.semibold))
                        }
                        .buttonStyle(.bordered)
                    } else {
                        Button {
                            PAXDelete.confirm(
                                message: L10n.DeviceRevokeMessage,
                                itemTitle: device.deviceName,
                                confirmTitle: L10n.DeviceRevokeConfirm
                            ) {
                                Task { await revoke(device) }
                            }
                        } label: {
                            Label { Text(L10n.DeviceActionRevoke) } icon: { PAXIcon("xmark.shield") }
                                .font(.caption.weight(.semibold))
                        }
                        .buttonStyle(.bordered)
                    }
                }
                .padding(.top, 4)
            }
        }
        .padding(.vertical, 4)
    }

    @ViewBuilder
    private func statusBadge(_ device: DeviceRecord) -> some View {
        if device.revoked {
            Text(L10n.DeviceStatusRevoked)
                .font(.caption2.weight(.semibold))
                .foregroundStyle(PAXTheme.danger)
        } else if device.online {
            Text(L10n.DeviceStatusLive)
                .font(.caption2.weight(.semibold))
                .foregroundStyle(PAXTheme.success)
        } else {
            Text(L10n.DeviceStatusInactive)
                .font(.caption2.weight(.semibold))
                .foregroundStyle(PAXTheme.textTertiary)
        }
    }

    private func chip(_ text: String, color: Color) -> some View {
        Text(text)
            .font(.caption2.weight(.semibold))
            .foregroundStyle(color)
            .padding(.horizontal, 8)
            .padding(.vertical, 3)
            .background(Capsule().fill(color.opacity(0.12)))
    }

    private func metaRow(_ title: String, _ value: String) -> some View {
        HStack(spacing: 8) {
            Text(title)
                .font(.caption2)
                .foregroundStyle(PAXTheme.textTertiary)
                .frame(width: 88, alignment: .leading)
            Text(value)
                .font(.caption)
                .foregroundStyle(PAXTheme.textSecondary)
        }
    }

    private func formatTimestamp(_ unix: Int) -> String {
        guard unix > 0 else { return "—" }
        let date = Date(timeIntervalSince1970: TimeInterval(unix))
        return date.formatted(date: .abbreviated, time: .shortened)
    }

    @discardableResult
    private func loadDevices() async -> Bool {
        guard loadGuard.tryEnter("devices-list") else { return false }
        defer { loadGuard.leave("devices-list") }
        guard let api = auth.api else { return false }
        isLoading = devices.isEmpty
        defer { isLoading = false }
        do {
            let response = try await api.fetchEmployeeDevices(
                userId: selectedEmployeeId,
                currentDeviceId: PAXDeviceInfo.deviceId
            )
            devices = response.devices
            errorMessage = nil
            return false
        } catch {
            errorMessage = error.localizedDescription
            return true
        }
    }

    private func revoke(_ device: DeviceRecord) async {
        guard let api = auth.api else { return }
        do {
            try await api.revokeDevice(deviceId: device.deviceId, userId: device.userId)
            PAXHaptics.warning()
            await loadDevices()
        } catch {
            errorMessage = error.localizedDescription
        }
    }

    private func approve(_ device: DeviceRecord) async {
        guard let api = auth.api else { return }
        do {
            try await api.approveDevice(deviceId: device.deviceId, userId: device.userId)
            PAXHaptics.success()
            confirmApprove = nil
            await loadDevices()
        } catch {
            errorMessage = error.localizedDescription
        }
    }
}
