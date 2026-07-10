import SwiftUI

struct LoginView: View {
    @EnvironmentObject private var auth: AuthStore
    @State private var siteURL = ""
    @State private var username = ""
    @State private var password = ""
    @State private var isLoading = false
    @State private var error: String?

    var body: some View {
        NavigationStack {
            ScrollView {
                VStack(spacing: 20) {
                    VStack(spacing: 12) {
                        Image(systemName: "bubble.left.and.bubble.right.fill")
                            .font(.system(size: 44))
                            .symbolRenderingMode(.hierarchical)
                            .foregroundStyle(.tint)
                        Text(L10n.LoginTitle)
                            .font(.title2.weight(.semibold))
                        Text(L10n.LoginSubtitle)
                            .font(.subheadline)
                            .foregroundStyle(.secondary)
                            .multilineTextAlignment(.center)
                    }
                    .padding(.top, 24)

                    VStack(spacing: 14) {
                        PAXField(title: L10n.LoginWebsite, icon: "globe", text: $siteURL, keyboardType: .URL)
                        PAXField(title: L10n.LoginUsername, icon: "person", text: $username, keyboardType: .emailAddress)
                        PAXField(title: L10n.LoginAppPassword, icon: "key", text: $password, isSecure: true)
                    }

                    if let error {
                        Text(error)
                            .font(.footnote)
                            .foregroundStyle(.red)
                            .multilineTextAlignment(.center)
                            .frame(maxWidth: .infinity)
                    }

                    PAXPrimaryButton(title: L10n.LoginSignIn, isLoading: isLoading) {
                        signIn()
                    }

                    VStack(spacing: 8) {
                        Text(L10n.LoginHint)
                            .font(.caption)
                            .foregroundStyle(.secondary)
                            .multilineTextAlignment(.center)
                        Link(L10n.LoginPrivacy, destination: PAXLegalLinks.privacyPolicy)
                        Link(L10n.LoginTerms, destination: PAXLegalLinks.impressum)
                    }
                }
                .padding(.horizontal, 20)
                .padding(.bottom, 24)
            }
            .scrollDismissesKeyboard(.interactively)
            .paxScreenBackground()
            .navigationBarTitleDisplayMode(.inline)
            .onAppear {
                siteURL = auth.siteURLString
                username = auth.username
                password = auth.appPassword
                PAXHaptics.prepare()
            }
        }
    }

    private func signIn() {
        guard !isLoading else { return }
        PAXKeyboard.dismiss()
        isLoading = true
        error = nil
        PAXHaptics.light()

        auth.siteURLString = siteURL
        auth.username = username
        auth.appPassword = password

        Task {
            do {
                try await auth.login()
                PAXHaptics.success()
            } catch {
                self.error = error.localizedDescription
                PAXHaptics.warning()
            }
            isLoading = false
        }
    }
}
