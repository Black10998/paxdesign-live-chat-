import SwiftUI

struct CustomerDeviceRecord: Decodable, Identifiable {
    var id: String { device_id }
    let device_id: String
    let device_name: String
    let device_model: String
    let os_version: String
    let app_version: String
    let first_login_at: Int
    let last_active_at: Int
    let is_current: Bool
    let trusted: Bool
    let push_registered: Bool
    let push_environment: String
    let online: Bool
}

struct CustomerDevicesResponse: Decodable {
    let devices: [CustomerDeviceRecord]
}

struct CustomerDeviceManagementView: View {
    @EnvironmentObject private var api: CustomerAPIClient
    @State private var devices: [CustomerDeviceRecord] = []
    @State private var isLoading = true
    @State private var errorMessage: String?
    @State private var confirmRevoke: CustomerDeviceRecord?
    @State private var confirmRevokeOthers = false

    var body: some View {
        List {
            if let errorMessage {
                Section {
                    Text(errorMessage)
                        .font(.caption)
                        .foregroundStyle(.red)
                }
            }

            if isLoading {
                Section {
                    ProgressView(String(localized: "Loading devices…"))
                        .frame(maxWidth: .infinity)
                }
            } else if devices.isEmpty {
                Section {
                    PAXContentUnavailableView(
                        String(localized: "No devices found"),
                        systemImage: "iphone.slash",
                        description: Text(String(localized: "Devices connected to your account will appear here."))
                    )
                }
            } else {
                Section(String(localized: "Connected devices")) {
                    ForEach(devices) { device in
                        deviceRow(device)
                    }
                }

                if devices.contains(where: { !$0.is_current }) {
                    Section {
                        Button(String(localized: "Sign out all other devices"), role: .destructive) {
                            confirmRevokeOthers = true
                        }
                    }
                }
            }
        }
        .navigationTitle(String(localized: "Devices"))
        .navigationBarTitleDisplayMode(.inline)
        .refreshable { await loadDevices() }
        .task { await loadDevices() }
        .confirmationDialog(
            String(localized: "Remove device?"),
            isPresented: Binding(
                get: { confirmRevoke != nil },
                set: { if !$0 { confirmRevoke = nil } }
            ),
            titleVisibility: .visible
        ) {
            if let device = confirmRevoke {
                Button(String(localized: "Sign out device"), role: .destructive) {
                    Task { await revoke(device) }
                }
            }
            Button(String(localized: "Cancel"), role: .cancel) { confirmRevoke = nil }
        } message: {
            if let device = confirmRevoke {
                Text(String(localized: "This will sign out \(device.device_name) from your account."))
            }
        }
        .confirmationDialog(
            String(localized: "Sign out all other devices?"),
            isPresented: $confirmRevokeOthers,
            titleVisibility: .visible
        ) {
            Button(String(localized: "Sign out others"), role: .destructive) {
                Task { await revokeOthers() }
            }
            Button(String(localized: "Cancel"), role: .cancel) {}
        }
    }

    @ViewBuilder
    private func deviceRow(_ device: CustomerDeviceRecord) -> some View {
        VStack(alignment: .leading, spacing: 8) {
            HStack(spacing: 12) {
                PAXDeviceGlyph(machine: device.device_model, size: 28)
                VStack(alignment: .leading, spacing: 2) {
                    Text(device.device_name)
                        .font(.subheadline.weight(.semibold))
                    Text(PAXDeviceModelMapper.friendlyName(for: device.device_model))
                        .font(.caption)
                        .foregroundStyle(PAXTheme.textSecondary)
                }
                Spacer()
                if device.is_current {
                    Text(String(localized: "This device"))
                        .font(.caption2.weight(.semibold))
                        .foregroundStyle(.blue)
                }
            }

            HStack(spacing: 6) {
                chip(device.online ? String(localized: "Online") : String(localized: "Offline"), color: device.online ? .green : .gray)
                chip(device.trusted ? String(localized: "Trusted") : String(localized: "Not trusted"), color: device.trusted ? .mint : .orange)
                chip(device.push_registered ? String(localized: "Notifications on") : String(localized: "Notifications off"), color: device.push_registered ? .green : .orange)
            }

            meta(String(localized: "System"), device.os_version)
            meta(String(localized: "App version"), device.app_version)
            meta(String(localized: "Last active"), formatTimestamp(device.last_active_at))
            meta(String(localized: "First login"), formatTimestamp(device.first_login_at))

            if !device.is_current {
                Button(String(localized: "Sign out device"), role: .destructive) {
                    confirmRevoke = device
                }
                .font(.caption.weight(.semibold))
            }
        }
        .padding(.vertical, 4)
    }

    private func chip(_ title: String, color: Color) -> some View {
        Text(title)
            .font(.caption2.weight(.semibold))
            .padding(.horizontal, 8)
            .padding(.vertical, 4)
            .background(color.opacity(0.14))
            .foregroundStyle(color)
            .clipShape(Capsule())
    }

    private func meta(_ label: String, _ value: String) -> some View {
        HStack {
            Text(label)
                .font(.caption)
                .foregroundStyle(PAXTheme.textSecondary)
            Spacer()
            Text(value.isEmpty ? "—" : value)
                .font(.caption)
        }
    }

    private func formatTimestamp(_ unix: Int) -> String {
        guard unix > 0 else { return "—" }
        let date = Date(timeIntervalSince1970: TimeInterval(unix))
        return date.formatted(date: .abbreviated, time: .shortened)
    }

    private func loadDevices() async {
        isLoading = devices.isEmpty
        errorMessage = nil
        defer { isLoading = false }
        do {
            devices = try await api.fetchDevices().devices
        } catch {
            errorMessage = (error as? CustomerAPIError)?.localizedDescription ?? error.localizedDescription
        }
    }

    private func revoke(_ device: CustomerDeviceRecord) async {
        do {
            try await api.revokeDevice(deviceId: device.device_id)
            confirmRevoke = nil
            await loadDevices()
        } catch {
            errorMessage = (error as? CustomerAPIError)?.localizedDescription ?? error.localizedDescription
        }
    }

    private func revokeOthers() async {
        do {
            try await api.revokeOtherDevices()
            confirmRevokeOthers = false
            await loadDevices()
        } catch {
            errorMessage = (error as? CustomerAPIError)?.localizedDescription ?? error.localizedDescription
        }
    }
}
