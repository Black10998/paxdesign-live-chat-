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
                        PAXAppMarkView(size: 108, showGlow: true)
                            .scaleEffect(appear ? 1 : 0.88)
                            .opacity(appear ? 1 : 0)

                        VStack(spacing: 8) {
                            Text(L10n.LoginTitle)
                                .font(.system(size: 28, weight: .bold, design: .rounded))
                            Text(L10n.LoginSubtitle)
                                .font(.subheadline.weight(.medium))
                                .foregroundStyle(PAXTheme.textSecondary)
                        }
                        .opacity(appear ? 1 : 0)
                        .offset(y: appear ? 0 : 10)
                    }

                    PAXGlassCard {
                        VStack(spacing: 18) {
                            PAXField(title: L10n.LoginWebsite, icon: "globe", text: $auth.siteURLString, keyboardType: .URL)
                            PAXField(title: L10n.LoginUsername, icon: "person.crop.circle", text: $auth.username, keyboardType: .emailAddress)
                            PAXField(title: L10n.LoginAppPassword, icon: "key.fill", text: $auth.appPassword, isSecure: true)

                            if let error {
                                Text(error)
                                    .font(.footnote)
                                    .foregroundStyle(PAXTheme.danger)
                                    .multilineTextAlignment(.center)
                                    .transition(.opacity.combined(with: .move(edge: .top)))
                            }

                            PAXPrimaryButton(
                                title: isLoading ? L10n.LoginSigningIn : L10n.LoginSignIn,
                                isLoading: isLoading
                            ) {
                                Task { await signIn() }
                            }
                        }
                    }
                    .padding(.horizontal, 20)
                    .opacity(appear ? 1 : 0)
                    .offset(y: appear ? 0 : 16)

                    Text(L10n.LoginHint)
                        .font(.caption)
                        .foregroundStyle(PAXTheme.textTertiary)
                        .multilineTextAlignment(.center)
                        .padding(.horizontal, 28)

                    VStack(spacing: 8) {
                        Link(L10n.LoginPrivacy, destination: PAXLegalLinks.privacyPolicy)
                        Link(L10n.LoginTerms, destination: PAXLegalLinks.impressum)
                    }
                    .font(.caption)
                    .padding(.horizontal, 28)

                    Spacer(minLength: 24)
                }
            }
            .scrollDismissesKeyboard(.interactively)
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
