import SwiftUI

struct AppLockSettingsView: View {
    @ObservedObject private var appLock = AppLockService.shared
    @State private var showSetPIN = false
    @State private var showChangePIN = false
    @State private var statusMessage: String?

    var body: some View {
        List {
            Section {
                Toggle(L10n.ApplockEnable, isOn: $appLock.lockEnabled)
            } footer: {
                Text(L10n.ApplockFooter)
            }

            if appLock.lockEnabled {
                Section(L10n.ApplockUnlockSection) {
                    if appLock.canUseBiometrics {
                        Toggle(appLock.biometricTypeLabel, isOn: $appLock.biometricEnabled)
                    } else {
                        Label(L10n.ApplockBiometryUnavailable, systemImage: "exclamationmark.triangle")
                            .font(.footnote)
                            .foregroundStyle(PAXTheme.textSecondary)
                    }

                    Toggle(L10n.ApplockAppPin, isOn: $appLock.pinEnabled)
                        .onChange(of: appLock.pinEnabled) { enabled in
                            if enabled && !appLock.hasPINConfigured() {
                                showSetPIN = true
                            } else if !enabled {
                                appLock.removePIN()
                            }
                        }

                    if appLock.pinEnabled {
                        Button(L10n.ApplockChangePin) { showChangePIN = true }
                    } else if !appLock.hasPINConfigured() {
                        Button(L10n.ApplockSetPin) { showSetPIN = true }
                    }
                }

                Section(L10n.ApplockAutoLock) {
                    Picker(L10n.ApplockAfterInactivity, selection: $appLock.autoLockInterval) {
                        ForEach(AppLockService.AutoLockInterval.allCases) { item in
                            Text(item.label).tag(item)
                        }
                    }
                    Toggle(L10n.ApplockLockOnReopen, isOn: $appLock.lockOnLaunch)
                }

                Section {
                    Button(L10n.ApplockLockNow) {
                        appLock.lockFromSettings()
                        statusMessage = L10n.ApplockLockedMessage
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
        .navigationTitle(L10n.ApplockSettingsTitle)
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
                Text(step == 1 ? L10n.ApplockEnterNewPin : L10n.ApplockConfirmPin)
                    .font(.headline)
                SecureField(L10n.ApplockPinField, text: step == 1 ? $pin : $confirm)
                    .keyboardType(.numberPad)
                    .textContentType(.oneTimeCode)
                    .multilineTextAlignment(.center)
                    .font(.title2.monospacedDigit())
                    .padding()
                    .paxGlassCardStyle(cornerRadius: 14, fillOpacity: 0.82, borderOpacity: 0.44, shadowOpacity: 0.1)
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
                    Button(L10n.CommonCancel) { dismiss(); onDone() }
                }
                ToolbarItem(placement: .confirmationAction) {
                    Button(L10n.CommonNext) { advance() }
                        .disabled((step == 1 ? pin : confirm).count < 4)
                }
            }
            .navigationTitle(mode == .create ? L10n.ApplockSetPin : L10n.ApplockChangePin)
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
