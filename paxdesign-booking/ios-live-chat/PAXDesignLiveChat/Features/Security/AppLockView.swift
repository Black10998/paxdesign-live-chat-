import SwiftUI

struct AppLockView: View {
    @ObservedObject private var appLock = AppLockService.shared
    @State private var pin = ""
    @State private var errorMessage: String?
    @State private var shake = false

    var body: some View {
        ZStack {
            Rectangle()
                .fill(.ultraThinMaterial)
                .ignoresSafeArea()
            PAXTheme.background.opacity(0.55).ignoresSafeArea()

            VStack(spacing: 28) {
                Spacer()

                VStack(spacing: 16) {
                    PAXAppMarkView(size: 72, showGlow: true)
                    Text("Gesperrt")
                        .font(.title2.weight(.semibold))
                    Text("Authentifizieren Sie sich, um fortzufahren.")
                        .font(.subheadline)
                        .foregroundStyle(PAXTheme.textSecondary)
                        .multilineTextAlignment(.center)
                        .padding(.horizontal, 32)
                }

                if appLock.biometricEnabled {
                    Button {
                        Task {
                            do {
                                if try await appLock.evaluateBiometrics() {
                                    appLock.unlock()
                                    PAXHaptics.success()
                                }
                            } catch {
                                // User cancelled — PIN remains available
                            }
                        }
                    } label: {
                        Label(appLock.canUseBiometrics ? appLock.biometricTypeLabel : "Gerätecode", systemImage: biometricIcon)
                            .font(.headline)
                            .frame(maxWidth: .infinity)
                            .padding(.vertical, 14)
                    }
                    .buttonStyle(.borderedProminent)
                    .tint(PAXTheme.accent)
                    .padding(.horizontal, 40)
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
        .onAppear {
            if appLock.biometricEnabled {
                Task { await appLock.attemptBiometricUnlock() }
            }
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
                                Image(systemName: "delete.left")
                                    .font(.title3)
                            } else {
                                Text(key)
                                    .font(.title2.weight(.medium))
                            }
                        }
                        .frame(maxWidth: .infinity)
                        .frame(height: 56)
                        .background(
                            RoundedRectangle(cornerRadius: 16, style: .continuous)
                                .fill(PAXTheme.surface.opacity(0.9))
                        )
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
            errorMessage = "Falscher PIN"
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
