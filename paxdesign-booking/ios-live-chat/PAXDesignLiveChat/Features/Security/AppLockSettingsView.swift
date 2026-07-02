import SwiftUI

struct AppLockSettingsView: View {
    @ObservedObject private var appLock = AppLockService.shared
    @State private var showSetPIN = false
    @State private var showChangePIN = false
    @State private var statusMessage: String?

    var body: some View {
        List {
            Section {
                Toggle("App-Sperre aktivieren", isOn: $appLock.lockEnabled)
            } footer: {
                Text("Schützt die App mit Face ID, Touch ID oder einem App-PIN nach Inaktivität oder beim erneuten Öffnen.")
            }

            if appLock.lockEnabled {
                Section("Entsperren") {
                    if appLock.canUseBiometrics {
                        Toggle(appLock.biometricTypeLabel, isOn: $appLock.biometricEnabled)
                    } else {
                        Label("Biometrie auf diesem Gerät nicht verfügbar", systemImage: "exclamationmark.triangle")
                            .font(.footnote)
                            .foregroundStyle(PAXTheme.textSecondary)
                    }

                    Toggle("App-PIN", isOn: $appLock.pinEnabled)
                        .onChange(of: appLock.pinEnabled) { enabled in
                            if enabled && !appLock.hasPINConfigured() {
                                showSetPIN = true
                            } else if !enabled {
                                appLock.removePIN()
                            }
                        }

                    if appLock.pinEnabled {
                        Button("PIN ändern") { showChangePIN = true }
                    } else if !appLock.hasPINConfigured() {
                        Button("PIN festlegen") { showSetPIN = true }
                    }
                }

                Section("Automatische Sperre") {
                    Picker("Nach Inaktivität", selection: $appLock.autoLockInterval) {
                        ForEach(AppLockService.AutoLockInterval.allCases) { item in
                            Text(item.label).tag(item)
                        }
                    }
                    Toggle("Beim erneuten Öffnen sperren", isOn: $appLock.lockOnLaunch)
                }

                Section {
                    Button("Jetzt sperren") {
                        appLock.lock()
                        statusMessage = "App wurde gesperrt."
                    }
                }
            }

            if let statusMessage {
                Section {
                    Text(statusMessage)
                        .font(.footnote)
                        .foregroundStyle(PAXTheme.textSecondary)
                }
            }
        }
        .navigationTitle("App-Sperre")
        .navigationBarTitleDisplayMode(.inline)
        .scrollContentBackground(.hidden)
        .background(PAXBackground())
        .sheet(isPresented: $showSetPIN) {
            SetPINView(mode: .create) { showSetPIN = false }
        }
        .sheet(isPresented: $showChangePIN) {
            SetPINView(mode: .change) { showChangePIN = false }
        }
    }
}

private struct SetPINView: View {
    enum Mode { case create, change }

    let mode: Mode
    let onDone: () -> Void

    @Environment(\.dismiss) private var dismiss
    @State private var pin = ""
    @State private var confirm = ""
    @State private var step = 1
    @State private var errorMessage: String?

    var body: some View {
        NavigationStack {
            VStack(spacing: 24) {
                Text(step == 1 ? "Neuen PIN eingeben" : "PIN bestätigen")
                    .font(.headline)
                SecureField("PIN", text: step == 1 ? $pin : $confirm)
                    .keyboardType(.numberPad)
                    .textContentType(.oneTimeCode)
                    .multilineTextAlignment(.center)
                    .font(.title2.monospacedDigit())
                    .padding()
                    .background(RoundedRectangle(cornerRadius: 14).fill(PAXTheme.surface))
                    .padding(.horizontal, 32)

                if let errorMessage {
                    Text(errorMessage).font(.footnote).foregroundStyle(PAXTheme.danger)
                }

                Spacer()
            }
            .padding(.top, 32)
            .background(PAXBackground())
            .toolbar {
                ToolbarItem(placement: .cancellationAction) {
                    Button("Abbrechen") { dismiss(); onDone() }
                }
                ToolbarItem(placement: .confirmationAction) {
                    Button("Weiter") { advance() }
                        .disabled((step == 1 ? pin : confirm).count < 4)
                }
            }
            .navigationTitle(mode == .create ? "PIN festlegen" : "PIN ändern")
            .navigationBarTitleDisplayMode(.inline)
        }
        .presentationDetents([.medium])
    }

    private func advance() {
        errorMessage = nil
        if step == 1 {
            guard pin.count >= 4 else {
                errorMessage = AppLockError.invalidPIN.localizedDescription
                return
            }
            step = 2
            return
        }
        guard pin == confirm else {
            errorMessage = AppLockError.pinMismatch.localizedDescription
            confirm = ""
            return
        }
        do {
            try AppLockService.shared.setPIN(pin)
            AppLockService.shared.pinEnabled = true
            PAXHaptics.success()
            dismiss()
            onDone()
        } catch {
            errorMessage = error.localizedDescription
        }
    }
}
