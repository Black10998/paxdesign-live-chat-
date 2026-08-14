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
    private var isDark: Bool { colorScheme == .dark }

    var body: some View {
        NavigationStack {
            ScrollView {
                VStack(alignment: .leading, spacing: PAXSpacing.sectionGap) {
                    authHero

                    VStack(spacing: PAXSpacing.sm) {
                        PAXField(title: L10n.LoginUsername, icon: "person", text: $username, keyboardType: .emailAddress)
                        PAXField(title: L10n.LoginPassword, icon: "lock", text: $password, isSecure: true)
                    }

                    if let error {
                        Text(error)
                            .font(PAXTypography.meta)
                            .foregroundStyle(PAXTheme.danger)
                            .multilineTextAlignment(.leading)
                            .frame(maxWidth: .infinity, alignment: .leading)
                            .padding(.horizontal, PAXSpacing.xxs)
                    }

                    PAXPrimaryButton(title: L10n.LoginSignIn, isLoading: isLoading) {
                        signIn()
                    }
                    .disabled(isBusy)

                    VStack(spacing: PAXSpacing.xs) {
                        Button(L10n.LoginCreateAccount) {
                            customerAuthMode = .register
                            showCustomerAuth = true
                        }
                        .font(PAXTypography.body.weight(.semibold))
                        .foregroundStyle(PAXTheme.link)

                        Button(L10n.LoginForgotPassword) {
                            customerAuthMode = .forgot
                            showCustomerAuth = true
                        }
                        .font(PAXTypography.meta.weight(.semibold))
                        .foregroundStyle(PAXRevolutColors.textSecondary(isDark: isDark))
                    }
                    .frame(maxWidth: .infinity)

                    authDivider

                    PAXSignInWithAppleButton(isLoading: isAppleLoading) { credential in
                        signInWithApple(credential)
                    } onFailure: { appleError in
                        error = appleError.localizedDescription
                        PAXHaptics.warning()
                    }

                    VStack(spacing: PAXSpacing.xs) {
                        Link(L10n.LoginPrivacy, destination: PAXLegalLinks.privacyPolicy)
                        Link(L10n.LoginTerms, destination: PAXLegalLinks.impressum)
                    }
                    .font(PAXTypography.caption)
                    .foregroundStyle(PAXTheme.link)
                    .frame(maxWidth: .infinity)
                }
                .padding(.horizontal, PAXSpacing.screenHorizontal)
                .padding(.top, PAXSpacing.xxl)
                .padding(.bottom, PAXSpacing.xxl)
            }
            .scrollDismissesKeyboard(.interactively)
            .paxScreenBackground()
            .navigationBarHidden(true)
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

    private var authHero: some View {
        VStack(alignment: .leading, spacing: PAXSpacing.md) {
            PAXAnimatedLogoView(markWidth: 148)
                .frame(maxWidth: .infinity, alignment: .leading)

            VStack(alignment: .leading, spacing: PAXSpacing.xs) {
                Text(L10n.LoginTitle)
                    .font(PAXTypography.titleLarge)
                    .foregroundStyle(PAXRevolutColors.textPrimary(isDark: isDark))
                Text(L10n.LoginSubtitle)
                    .font(PAXTypography.body)
                    .foregroundStyle(PAXRevolutColors.textSecondary(isDark: isDark))
                    .fixedSize(horizontal: false, vertical: true)
            }
        }
        .frame(maxWidth: .infinity, alignment: .leading)
    }

    private var authDivider: some View {
        HStack(spacing: PAXSpacing.sm) {
            Rectangle()
                .fill(PAXRevolutColors.divider(isDark: isDark))
                .frame(height: 1)
            Text(String(localized: "or"))
                .font(PAXTypography.meta)
                .foregroundStyle(PAXRevolutColors.textSecondary(isDark: isDark))
            Rectangle()
                .fill(PAXRevolutColors.divider(isDark: isDark))
                .frame(height: 1)
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
}

extension Notification.Name {
    static let paxEmailVerificationDeepLink = Notification.Name("paxEmailVerificationDeepLink")
    static let paxInteractiveLoginSucceeded = Notification.Name("paxInteractiveLoginSucceeded")
}
