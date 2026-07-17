import SwiftUI

struct LoginView: View {
    @EnvironmentObject private var auth: AuthStore
    @ObservedObject private var customerSession = CustomerSessionController.shared
    @State private var siteURL = ""
    @State private var username = ""
    @State private var password = ""
    @State private var isLoading = false
    @State private var error: String?
    @State private var customerAuthMode: CustomerAuthContainerView.AuthMode?
    @State private var showCustomerAuth = false

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
                    customerSession.auth.siteURL = siteURL.isEmpty ? auth.siteURLString : siteURL
                    customerSession.api.configure(baseURL: customerSession.auth.siteURL, auth: customerSession.auth)
                }
            }
            .onAppear {
                siteURL = auth.siteURLString.isEmpty ? "https://paxdesign.at" : auth.siteURLString
                username = auth.username
                password = auth.appPassword
                PAXHaptics.prepare()
            }
        }
    }

    @ViewBuilder
    private var customerAuthSheetContent: some View {
        switch customerAuthMode ?? .register {
        case .register:
            CustomerRegisterView(onDone: {
                customerAuthMode = .verify
            })
        case .forgot:
            CustomerForgotPasswordView(onDone: {
                showCustomerAuth = false
            })
        case .verify:
            CustomerVerifyEmailView(onDone: {
                showCustomerAuth = false
            })
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
