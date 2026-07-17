import SwiftUI

struct LoginView: View {
    @EnvironmentObject private var auth: AuthStore
    @ObservedObject private var customerSession = CustomerSessionController.shared
    @State private var username = ""
    @State private var password = ""
    @State private var isLoading = false
    @State private var error: String?
    @State private var customerAuthMode: CustomerAuthContainerView.AuthMode?
    @State private var showCustomerAuth = false
    @State private var pendingVerifyEmail = ""

    var body: some View {
        NavigationStack {
            ScrollView {
                VStack(spacing: 20) {
                    VStack(spacing: 12) {
                        PAXIcon("bubble.left.and.bubble.right.fill", size: .hero)
                        Text(L10n.LoginTitle)
                            .font(.title2.weight(.semibold))
                        Text(L10n.LoginSubtitle)
                            .font(.subheadline)
                            .foregroundStyle(.secondary)
                            .multilineTextAlignment(.center)
                    }
                    .padding(.top, 24)

                    VStack(spacing: 14) {
                        PAXField(title: L10n.LoginUsername, icon: "person", text: $username, keyboardType: .emailAddress)
                        PAXField(title: L10n.LoginPassword, icon: "lock", text: $password, isSecure: true)
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

                    VStack(spacing: 10) {
                        Button(L10n.LoginCreateAccount) {
                            customerAuthMode = .register
                            showCustomerAuth = true
                        }
                        .font(.subheadline)

                        Button(L10n.LoginForgotPassword) {
                            customerAuthMode = .forgot
                            showCustomerAuth = true
                        }
                        .font(.subheadline)
                        .foregroundStyle(.secondary)
                    }

                    VStack(spacing: 8) {
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
        guard !isLoading else { return }
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
}

extension Notification.Name {
    static let paxEmailVerificationDeepLink = Notification.Name("paxEmailVerificationDeepLink")
    static let paxInteractiveLoginSucceeded = Notification.Name("paxInteractiveLoginSucceeded")
}
