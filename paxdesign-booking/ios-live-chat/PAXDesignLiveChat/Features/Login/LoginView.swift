import SwiftUI
import AuthenticationServices

struct LoginView: View {
    @EnvironmentObject private var auth: AuthStore
    @Environment(\.colorScheme) private var colorScheme
    @ObservedObject private var customerSession = CustomerSessionController.shared
    @State private var username = ""
    @State private var password = ""
    @State private var isLoading = false
    @State private var isAppleLoading = false
    @State private var error: String?
    @State private var customerAuthMode: CustomerAuthContainerView.AuthMode?
    @State private var showCustomerAuth = false
    @State private var pendingVerifyEmail = ""

    private var isBusy: Bool { isLoading || isAppleLoading }

    var body: some View {
        NavigationStack {
            GeometryReader { proxy in
                ScrollView {
                    VStack(spacing: 0) {
                        Spacer(minLength: max(24, (proxy.size.height - contentHeightEstimate) / 2))

                        loginCard
                            .frame(maxWidth: 420)
                            .frame(maxWidth: .infinity)

                        Spacer(minLength: max(24, (proxy.size.height - contentHeightEstimate) / 2))
                    }
                    .padding(.horizontal, 24)
                    .frame(minHeight: proxy.size.height)
                }
            }
            .scrollDismissesKeyboard(.interactively)
            .paxScreenBackground()
            .navigationBarTitleDisplayMode(.inline)
            .sheet(isPresented: $showCustomerAuth) {
                NavigationStack {
                    customerAuthSheetContent
                        .environmentObject(customerSession.auth)
                        .environmentObject(customerSession.api)
                        .toolbar {
                            ToolbarItem(placement: .cancellationAction) {
                                Button(L10n.CommonCancel) { showCustomerAuth = false }
                            }
                        }
                }
                .onAppear {
                    customerSession.api.useDefaultServer()
                    customerSession.auth.siteURL = AppServerConfig.siteURL
                }
            }
            .onAppear {
                username = auth.username
                password = auth.accountPassword
                PAXHaptics.prepare()
            }
            .onReceive(NotificationCenter.default.publisher(for: .paxEmailVerificationDeepLink)) { note in
                if note.userInfo?["verified"] as? Bool == true {
                    error = String(localized: "Email verified. You can sign in now.")
                    return
                }
                if let err = note.userInfo?["error"] as? String {
                    error = err
                }
                if let email = note.userInfo?["email"] as? String {
                    pendingVerifyEmail = email
                }
                customerAuthMode = .verify
                showCustomerAuth = true
            }
        }
    }

    private var contentHeightEstimate: CGFloat { 560 }

    private var loginCard: some View {
        VStack(spacing: 22) {
            PAXAuthHeroView(
                style: .animatedLogo,
                title: L10n.LoginTitle,
                subtitle: L10n.LoginSubtitle,
                markWidth: 128,
                showsTitle: false
            )

            VStack(spacing: 14) {
                PAXSignInWithAppleButton(isLoading: isAppleLoading) { credential in
                    signInWithApple(credential)
                } onFailure: { appleError in
                    error = appleError.localizedDescription
                    PAXHaptics.warning()
                }

                HStack(spacing: 12) {
                    Rectangle().fill(PAXTheme.border.opacity(0.45)).frame(height: 1)
                    Text(String(localized: "or"))
                        .font(.footnote)
                        .foregroundStyle(PAXTheme.textSecondary)
                    Rectangle().fill(PAXTheme.border.opacity(0.45)).frame(height: 1)
                }

                VStack(spacing: 12) {
                    PAXField(title: L10n.LoginUsername, icon: "person", text: $username, keyboardType: .emailAddress)
                    PAXField(title: L10n.LoginPassword, icon: "lock", text: $password, isSecure: true)
                }
            }

            if let error {
                Text(error)
                    .font(.footnote)
                    .foregroundStyle(PAXTheme.danger)
                    .multilineTextAlignment(.center)
                    .frame(maxWidth: .infinity)
            }

            PAXPrimaryButton(title: L10n.LoginSignIn, isLoading: isLoading) {
                signIn()
            }
            .disabled(isBusy)

            VStack(spacing: 10) {
                Button(L10n.LoginCreateAccount) {
                    customerAuthMode = .register
                    showCustomerAuth = true
                }
                .font(.subheadline)
                .foregroundStyle(PAXTheme.link)

                Button(L10n.LoginForgotPassword) {
                    customerAuthMode = .forgot
                    showCustomerAuth = true
                }
                .font(.subheadline)
                .foregroundStyle(PAXTheme.textSecondary)
            }

            VStack(spacing: 8) {
                Link(L10n.LoginPrivacy, destination: PAXLegalLinks.privacyPolicy)
                    .foregroundStyle(PAXTheme.link)
                Link(L10n.LoginTerms, destination: PAXLegalLinks.impressum)
                    .foregroundStyle(PAXTheme.link)
            }
            .font(.footnote)
        }
        .padding(.vertical, 8)
    }

    @ViewBuilder
    private var customerAuthSheetContent: some View {
        switch customerAuthMode ?? .register {
        case .register:
            CustomerRegisterView(onDone: { email in
                pendingVerifyEmail = email
                customerAuthMode = .verify
            })
        case .forgot:
            CustomerForgotPasswordView(onDone: {
                showCustomerAuth = false
            })
        case .verify:
            CustomerVerifyEmailView(
                email: pendingVerifyEmail,
                onDone: {
                    showCustomerAuth = false
                }
            )
        case .login:
            EmptyView()
        }
    }

    private func signIn() {
        guard !isBusy else { return }
        PAXKeyboard.dismiss()
        isLoading = true
        error = nil
        PAXHaptics.light()

        auth.username = username
        auth.accountPassword = password

        Task {
            do {
                try await auth.login()
                PAXHaptics.success()
            } catch {
                self.error = error.localizedDescription
                if error.localizedDescription.localizedCaseInsensitiveContains("verify") {
                    pendingVerifyEmail = username
                    customerAuthMode = .verify
                    showCustomerAuth = true
                }
                PAXHaptics.warning()
            }
            isLoading = false
        }
    }

    private func signInWithApple(_ credential: ASAuthorizationAppleIDCredential) {
        guard !isBusy else { return }
        guard let identityToken = credential.identityTokenString else {
            error = SignInWithAppleError.missingIdentityToken.localizedDescription
            return
        }

        PAXKeyboard.dismiss()
        isAppleLoading = true
        error = nil
        PAXHaptics.light()

        Task {
            do {
                try await auth.loginWithApple(
                    identityToken: identityToken,
                    authorizationCode: credential.authorizationCodeString,
                    fullName: credential.fullName,
                    email: credential.email
                )
                PAXHaptics.success()
            } catch {
                self.error = error.localizedDescription
                PAXHaptics.warning()
            }
            isAppleLoading = false
        }
    }
}

extension Notification.Name {
    static let paxEmailVerificationDeepLink = Notification.Name("paxEmailVerificationDeepLink")
    static let paxInteractiveLoginSucceeded = Notification.Name("paxInteractiveLoginSucceeded")
}
