import SwiftUI

struct DeviceManagementView: View {
    @EnvironmentObject private var auth: AuthStore
    @State private var devices: [DeviceRecord] = []
    @State private var isLoading = true
    @State private var errorMessage: String?
    @State private var selectedEmployeeId: Int?
    @State private var confirmRevoke: DeviceRecord?
    @State private var confirmApprove: DeviceRecord?
    @State private var liveRefreshTask: Task<Void, Never>?

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
                    VStack(spacing: 12) {
                        PAXTimelineLoaderCard(status: "Geräte werden synchronisiert")
                        ForEach(0..<3, id: \.self) { _ in
                            deviceSkeletonRow
                        }
                    }
                }
            } else if devices.isEmpty {
                Section {
                    VStack(spacing: 10) {
                        Image(systemName: "iphone.slash")
                            .font(.system(size: 36, weight: .light))
                            .foregroundStyle(PAXTheme.textTertiary)
                        Text("Keine Geräte")
                            .font(.headline)
                        Text("Noch keine registrierten Geräte gefunden.")
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
                        Section(employeeDevices.first?.employeeName ?? "Mitarbeiter") {
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
        .navigationTitle("Geräteverwaltung")
        .navigationBarTitleDisplayMode(.inline)
        .refreshable { await loadDevices() }
        .task {
            await loadDevices()
            startRealtimeRefresh()
        }
        .onDisappear { stopRealtimeRefresh() }
        .confirmationDialog(
            "Gerät abmelden?",
            isPresented: Binding(
                get: { confirmRevoke != nil },
                set: { if !$0 { confirmRevoke = nil } }
            ),
            titleVisibility: .visible
        ) {
            if let device = confirmRevoke {
                Button("Abmelden erzwingen", role: .destructive) {
                    Task { await revoke(device) }
                }
            }
            Button("Abbrechen", role: .cancel) { confirmRevoke = nil }
        } message: {
            if let device = confirmRevoke {
                Text("\(device.deviceName) wird sofort abgemeldet.")
            }
        }
        .confirmationDialog(
            "Gerät freigeben?",
            isPresented: Binding(
                get: { confirmApprove != nil },
                set: { if !$0 { confirmApprove = nil } }
            ),
            titleVisibility: .visible
        ) {
            if let device = confirmApprove {
                Button("Freigeben") {
                    Task { await approve(device) }
                }
            }
            Button("Abbrechen", role: .cancel) { confirmApprove = nil }
        } message: {
            if let device = confirmApprove {
                Text("\(device.deviceName) darf wieder Anfragen senden.")
            }
        }
    }

    private func startRealtimeRefresh() {
        liveRefreshTask?.cancel()
        liveRefreshTask = Task {
            while !Task.isCancelled {
                try? await Task.sleep(nanoseconds: 20_000_000_000)
                guard !Task.isCancelled else { return }
                await loadDevices()
            }
        }
    }

    private func stopRealtimeRefresh() {
        liveRefreshTask?.cancel()
        liveRefreshTask = nil
    }

    private func deviceRow(_ device: DeviceRecord) -> some View {
        VStack(alignment: .leading, spacing: 8) {
            HStack {
                Image(systemName: "iphone")
                    .foregroundStyle(device.revoked ? PAXTheme.danger : PAXTheme.accent)
                Text(device.deviceName)
                    .font(.subheadline.weight(.semibold))
                Spacer()
                statusBadge(device)
            }

            HStack(spacing: 6) {
                if device.isCurrent {
                    chip("Dieses Gerät", color: .blue)
                }
                chip(device.online ? "Online" : "Offline", color: device.online ? .green : .gray)
                chip(device.approved && !device.revoked ? "Freigegeben" : "Nicht freigegeben", color: device.approved && !device.revoked ? .mint : .orange)
            }

            if !device.deviceModel.isEmpty {
                metaRow("Modell", device.deviceModel)
            }
            metaRow("System", device.osVersion)
            metaRow("App", device.appVersion)
            metaRow("Erstanmeldung", formatTimestamp(device.firstLoginAt))
            metaRow("Zuletzt aktiv", formatTimestamp(device.lastActiveAt))
            if !device.ipAddress.isEmpty {
                metaRow("IP-Adresse", device.ipAddress)
            }
            if !device.location.isEmpty {
                metaRow("Standort", device.location)
            }

            if canManage {
                HStack(spacing: 10) {
                    if device.revoked || !device.approved {
                        Button {
                            confirmApprove = device
                        } label: {
                            Label("Freigeben", systemImage: "checkmark.shield")
                                .font(.caption.weight(.semibold))
                        }
                        .buttonStyle(.bordered)
                    } else {
                        Button(role: .destructive) {
                            confirmRevoke = device
                        } label: {
                            Label("Widerrufen", systemImage: "xmark.shield")
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

    private var deviceSkeletonRow: some View {
        VStack(alignment: .leading, spacing: 10) {
            HStack(spacing: 10) {
                PAXSkeletonCircle(size: 24)
                PAXSkeletonBlock(width: 156, height: 11, cornerRadius: 8)
                Spacer(minLength: 0)
                PAXSkeletonBlock(width: 64, height: 9, cornerRadius: 999)
            }
            HStack(spacing: 8) {
                PAXSkeletonBlock(width: 70, height: 18, cornerRadius: 999)
                PAXSkeletonBlock(width: 66, height: 18, cornerRadius: 999)
                PAXSkeletonBlock(width: 90, height: 18, cornerRadius: 999)
            }
            VStack(alignment: .leading, spacing: 6) {
                PAXSkeletonBlock(width: 220, height: 8, cornerRadius: 7)
                PAXSkeletonBlock(width: 185, height: 8, cornerRadius: 7)
                PAXSkeletonBlock(width: 204, height: 8, cornerRadius: 7)
                PAXSkeletonBlock(width: 170, height: 8, cornerRadius: 7)
            }
            HStack(spacing: 8) {
                PAXSkeletonBlock(width: 92, height: 24, cornerRadius: 8)
                PAXSkeletonBlock(width: 86, height: 24, cornerRadius: 8)
            }
            .padding(.top, 2)
        }
        .padding(.vertical, 6)
    }

    @ViewBuilder
    private func statusBadge(_ device: DeviceRecord) -> some View {
        if device.revoked {
            Text("Widerrufen")
                .font(.caption2.weight(.semibold))
                .foregroundStyle(PAXTheme.danger)
        } else if device.online {
            Text("Live")
                .font(.caption2.weight(.semibold))
                .foregroundStyle(PAXTheme.success)
        } else {
            Text("Inaktiv")
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

    private func loadDevices() async {
        guard let api = auth.api else { return }
        isLoading = true
        defer { isLoading = false }
        do {
            let response = try await api.fetchEmployeeDevices(
                userId: selectedEmployeeId,
                currentDeviceId: PAXDeviceInfo.deviceId
            )
            devices = response.devices
            errorMessage = nil
        } catch {
            errorMessage = error.localizedDescription
        }
    }

    private func revoke(_ device: DeviceRecord) async {
        guard let api = auth.api else { return }
        do {
            try await api.revokeDevice(deviceId: device.deviceId, userId: device.userId)
            PAXHaptics.warning()
            confirmRevoke = nil
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
