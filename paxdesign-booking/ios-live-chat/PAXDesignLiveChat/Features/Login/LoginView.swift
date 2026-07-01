import SwiftUI

struct LoginView: View {
    @EnvironmentObject private var auth: AuthStore
    @State private var isLoading = false
    @State private var error: String?
    @State private var appear = false

    var body: some View {
        ZStack {
            PAXBackground()

            ScrollView(showsIndicators: false) {
                VStack(spacing: 28) {
                    Spacer(minLength: 24)

                    VStack(spacing: 18) {
                        PAXClockLogo(size: 108, animate: true)
                            .scaleEffect(appear ? 1 : 0.88)
                            .opacity(appear ? 1 : 0)

                        VStack(spacing: 8) {
                            Text("PAXDesign Live Chat")
                                .font(.system(size: 30, weight: .bold, design: .rounded))
                            Text("Offizielle Administrator-App")
                                .font(.subheadline.weight(.medium))
                                .foregroundStyle(PAXTheme.textSecondary)
                        }
                        .opacity(appear ? 1 : 0)
                        .offset(y: appear ? 0 : 10)
                    }

                    PAXGlassCard {
                        VStack(spacing: 18) {
                            PAXField(title: "Website", icon: "globe", text: $auth.siteURLString)
                            PAXField(title: "Benutzername oder E-Mail", icon: "person.crop.circle", text: $auth.username)
                            PAXField(title: "Application Password", icon: "key.fill", text: $auth.appPassword, isSecure: true)

                            if let error {
                                Text(error)
                                    .font(.footnote)
                                    .foregroundStyle(PAXTheme.danger)
                                    .multilineTextAlignment(.center)
                                    .transition(.opacity.combined(with: .move(edge: .top)))
                            }

                            PAXPrimaryButton(title: isLoading ? "Anmelden …" : "Anmelden", isLoading: isLoading) {
                                Task { await signIn() }
                            }
                        }
                    }
                    .padding(.horizontal, 20)
                    .opacity(appear ? 1 : 0)
                    .offset(y: appear ? 0 : 16)

                    Text("Application Password aus WordPress kopieren (Leerzeichen werden automatisch entfernt).")
                        .font(.caption)
                        .foregroundStyle(PAXTheme.textTertiary)
                        .multilineTextAlignment(.center)
                        .padding(.horizontal, 28)

                    Spacer(minLength: 24)
                }
            }
        }
        .onAppear {
            withAnimation(PAXTheme.spring.delay(0.05)) {
                appear = true
            }
        }
    }

    private func signIn() async {
        isLoading = true
        error = nil
        PAXHaptics.light()
        defer { isLoading = false }
        do {
            try await auth.login()
            PAXHaptics.success()
        } catch {
            self.error = error.localizedDescription
            PAXHaptics.warning()
        }
    }
}
