import SwiftUI

struct AppLockView: View {
    @ObservedObject private var appLock = AppLockService.shared
    @State private var pin = ""
    @State private var errorMessage: String?
    @State private var shake = false

    var body: some View {
        ZStack {
            PAXTheme.background.ignoresSafeArea()

            VStack(spacing: 28) {
                Spacer()

                VStack(spacing: 16) {
                    PAXAppMarkView(size: 72, showGlow: true)
                    Text(L10n.ApplockLocked)
                        .font(PAXTypography.titleLarge)
                    Text(L10n.ApplockPrompt)
                        .font(PAXTypography.body)
                        .foregroundStyle(PAXTheme.textSecondary)
                        .multilineTextAlignment(.center)
                        .padding(.horizontal, 32)
                }

                if appLock.biometricEnabled {
                    PAXRevolutPrimaryButton(
                        title: appLock.canUseBiometrics ? appLock.biometricTypeLabel : L10n.ApplockDevicePasscode
                    ) {
                        Task {
                            let success = await appLock.requestDeviceAuthentication()
                            if success { PAXHaptics.success() }
                        }
                    }
                    .padding(.horizontal, 24)
                }

                if appLock.pinEnabled {
                    pinDots

                    if let errorMessage {
                        Text(errorMessage)
                            .font(.footnote)
                            .foregroundStyle(PAXTheme.danger)
                            .transition(.opacity)
                    }

                    pinPad
                        .padding(.horizontal, 24)
                        .modifier(ShakeEffect(animating: shake))
                }

                Spacer()
            }
        }
        .task(id: appLock.lockEpoch) {
            guard appLock.isLocked, !appLock.isUnlocked else { return }
            await appLock.requestBiometricUnlockIfNeeded()
        }
    }

    private var biometricIcon: String {
        switch appLock.biometricTypeLabel {
        case "Face ID": return "faceid"
        case "Touch ID": return "touchid"
        default: return "lock.shield"
        }
    }

    private var pinDots: some View {
        HStack(spacing: 14) {
            ForEach(0..<6, id: \.self) { index in
                Circle()
                    .strokeBorder(PAXTheme.border, lineWidth: 1.5)
                    .background(Circle().fill(index < pin.count ? PAXTheme.accent : .clear))
                    .frame(width: 12, height: 12)
            }
        }
        .padding(.top, 8)
    }

    private var pinPad: some View {
        let keys = ["1", "2", "3", "4", "5", "6", "7", "8", "9", "", "0", "⌫"]
        return LazyVGrid(columns: Array(repeating: GridItem(.flexible(), spacing: 12), count: 3), spacing: 12) {
            ForEach(keys, id: \.self) { key in
                if key.isEmpty {
                    Color.clear.frame(height: 56)
                } else {
                    Button {
                        handleKey(key)
                    } label: {
                        Group {
                            if key == "⌫" {
                                PAXIcon("delete.left", size: .card)
                            } else {
                                Text(key)
                                    .font(.title2.weight(.medium))
                            }
                        }
                        .frame(maxWidth: .infinity)
                        .frame(height: 56)
                        .paxGlassCardStyle(cornerRadius: 16, fillOpacity: 0.82, borderOpacity: 0.44, shadowOpacity: 0.1)
                    }
                    .buttonStyle(PAXPressButtonStyle())
                }
            }
        }
    }

    private func handleKey(_ key: String) {
        errorMessage = nil
        if key == "⌫" {
            if !pin.isEmpty { pin.removeLast() }
            return
        }
        guard pin.count < 6 else { return }
        pin.append(key)
        PAXHaptics.light()

        guard pin.count >= 4, appLock.pinEnabled else { return }
        if appLock.verifyPIN(pin) {
            appLock.unlock()
            pin = ""
            PAXHaptics.success()
        } else if pin.count == 6 {
            errorMessage = L10n.ApplockWrongPin
            withAnimation(.default) { shake = true }
            PAXHaptics.warning()
            DispatchQueue.main.asyncAfter(deadline: .now() + 0.45) {
                pin = ""
                shake = false
            }
        }
    }
}

private struct ShakeEffect: GeometryEffect {
    var animating: Bool
    var amount: CGFloat = 8

    var animatableData: CGFloat {
        get { animating ? 1 : 0 }
        set { _ = newValue }
    }

    func effectValue(size: CGSize) -> ProjectionTransform {
        let offset = animating ? sin(animatableData * .pi * 4) * amount : 0
        return ProjectionTransform(CGAffineTransform(translationX: offset, y: 0))
    }
}
