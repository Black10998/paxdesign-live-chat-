import SwiftUI

struct DeviceManagementView: View {
    @EnvironmentObject private var auth: AuthStore
    @State private var devices: [DeviceRecord] = []
    @State private var isLoading = true
    @State private var errorMessage: String?
    @State private var selectedEmployeeId: Int?
    @State private var confirmRevoke: DeviceRecord?

    private var canManage: Bool { auth.canManageUsers }

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
                    HStack {
                        Spacer()
                        ProgressView()
                        Spacer()
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
        .task { await loadDevices() }
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
                Text("\(device.deviceName) wird sofort abgemeldet und kann sich nicht mehr anmelden, bis ein Administrator es freigibt.")
            }
        }
    }

    private func deviceRow(_ device: DeviceRecord) -> some View {
        VStack(alignment: .leading, spacing: 8) {
            HStack {
                Image(systemName: "iphone")
                    .foregroundStyle(device.revoked ? PAXTheme.danger : PAXTheme.accent)
                Text(device.deviceName)
                    .font(.subheadline.weight(.semibold))
                Spacer()
                if device.revoked {
                    Text("Widerrufen")
                        .font(.caption2.weight(.semibold))
                        .foregroundStyle(PAXTheme.danger)
                }
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

            if canManage && !device.revoked {
                Button(role: .destructive) {
                    confirmRevoke = device
                } label: {
                    Label("Gerät abmelden", systemImage: "xmark.circle")
                        .font(.caption.weight(.semibold))
                }
                .padding(.top, 4)
            }
        }
        .padding(.vertical, 4)
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
            let response = try await api.fetchEmployeeDevices(userId: selectedEmployeeId)
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
}
