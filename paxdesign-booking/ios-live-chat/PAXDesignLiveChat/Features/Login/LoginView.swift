import SwiftUI
import AuthenticationServices

struct LoginView: View {
    @EnvironmentObject private var auth: AuthStore
    @ObservedObject private var customerSession = CustomerSessionController.shared
    @State private var username = ""
    @State private var password = ""
    @State private var isLoading = false
    @State private var isAppleLoading = false
    @State private var isGitHubLoading = false
    @State private var error: String?
    @State private var customerAuthMode: CustomerAuthContainerView.AuthMode?
    @State private var showCustomerAuth = false
    @State private var pendingVerifyEmail = ""

    private var isBusy: Bool { isLoading || isAppleLoading || isGitHubLoading }

    var body: some View {
        NavigationStack {
            ScrollView {
                VStack(alignment: .leading, spacing: 0) {
                    PAXAnimatedLogoView(markWidth: 168)
                        .frame(maxWidth: .infinity)
                        .padding(.top, 28)
                        .padding(.bottom, 28)

                    Text(L10n.LoginTitle)
                        .font(PAXTypography.titleLarge)
                        .foregroundStyle(PAXTheme.textPrimary)
                        .frame(maxWidth: .infinity, alignment: .leading)
                    Text(L10n.LoginSubtitle)
                        .font(PAXTypography.body)
                        .foregroundStyle(PAXTheme.textSecondary)
                        .padding(.top, 8)
                        .padding(.bottom, 28)

                    VStack(spacing: 12) {
                        PAXRevolutField(
                            title: L10n.LoginUsername,
                            systemImage: "person",
                            text: $username,
                            keyboardType: .emailAddress,
                            textContentType: .username
                        )
                        PAXRevolutField(
                            title: L10n.LoginPassword,
                            systemImage: "lock",
                            text: $password,
                            isSecure: true,
                            textContentType: .password
                        )
                    }

                    if let error {
                        Text(error)
                            .font(PAXTypography.meta)
                            .foregroundStyle(PAXTheme.danger)
                            .padding(.top, 12)
                    }

                    PAXRevolutPrimaryButton(title: L10n.LoginSignIn, isLoading: isLoading) {
                        signIn()
                    }
                    .disabled(isBusy)
                    .padding(.top, 20)

                    HStack {
                        Button(L10n.LoginCreateAccount) {
                            customerAuthMode = .register
                            showCustomerAuth = true
                        }
                        .font(PAXTypography.meta.weight(.semibold))
                        .foregroundStyle(PAXTheme.link)
                        Spacer()
                        Button(L10n.LoginForgotPassword) {
                            customerAuthMode = .forgot
                            showCustomerAuth = true
                        }
                        .font(PAXTypography.meta.weight(.semibold))
                        .foregroundStyle(PAXTheme.textSecondary)
                    }
                    .padding(.top, 16)

                    HStack(spacing: 12) {
                        Rectangle().fill(PAXTheme.divider).frame(height: 1)
                        Text(String(localized: "or"))
                            .font(PAXTypography.meta)
                            .foregroundStyle(PAXTheme.textTertiary)
                        Rectangle().fill(PAXTheme.divider).frame(height: 1)
                    }
                    .padding(.vertical, 24)

                    PAXSignInWithAppleButton(isLoading: isAppleLoading) { credential in
                        signInWithApple(credential)
                    } onFailure: { appleError in
                        error = appleError.localizedDescription
                        PAXHaptics.warning()
                    }
                    .padding(.bottom, 12)

                    PAXContinueWithGitHubButton(isLoading: isGitHubLoading) {
                        signInWithGitHub()
                    }

                    HStack(spacing: 16) {
                        Link(L10n.LoginPrivacy, destination: PAXLegalLinks.privacyPolicy)
                        Link(L10n.LoginTerms, destination: PAXLegalLinks.impressum)
                    }
                    .font(PAXTypography.caption)
                    .foregroundStyle(PAXTheme.link)
                    .frame(maxWidth: .infinity)
                    .padding(.top, 28)
                    .padding(.bottom, 24)
                }
                .padding(.horizontal, PAXSpacing.screenHorizontal)
                .frame(maxWidth: 480)
                .frame(maxWidth: .infinity)
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

    private func signInWithGitHub() {
        guard !isBusy else { return }
        PAXKeyboard.dismiss()
        isGitHubLoading = true
        error = nil
        PAXHaptics.light()

        Task {
            do {
                try await auth.loginWithGitHub()
                PAXHaptics.success()
            } catch {
                if (error as? GitHubOAuthError) != .cancelled {
                    self.error = error.localizedDescription
                    PAXHaptics.warning()
                }
            }
            isGitHubLoading = false
        }
    }
}

extension Notification.Name {
    static let paxEmailVerificationDeepLink = Notification.Name("paxEmailVerificationDeepLink")
    static let paxInteractiveLoginSucceeded = Notification.Name("paxInteractiveLoginSucceeded")
}
