import SwiftUI

struct CustomerAuthContainerView: View {
    @EnvironmentObject private var auth: CustomerAuthStore
    @EnvironmentObject private var api: CustomerAPIClient
    @State private var mode: AuthMode = .login

    enum AuthMode {
        case login, register, forgot, verify
    }

    var body: some View {
        NavigationStack {
            Group {
                switch mode {
                case .login:
                    CustomerLoginView(
                        onRegister: { mode = .register },
                        onForgot: { mode = .forgot }
                    )
                case .register:
                    CustomerRegisterView(onDone: { _ in mode = .verify })
                case .forgot:
                    CustomerForgotPasswordView(onDone: { mode = .login })
                case .verify:
                    CustomerVerifyEmailView(onDone: { mode = .login })
                }
            }
            .toolbar {
                if mode != .login {
                    ToolbarItem(placement: .cancellationAction) {
                        Button(String(localized: "Back")) { mode = .login }
                    }
                }
            }
        }
    }
}

struct CustomerRegisterView: View {
    @EnvironmentObject private var api: CustomerAPIClient
    var onDone: (String) -> Void
    @State private var name = ""
    @State private var email = ""
    @State private var password = ""
    @State private var message: String?
    @State private var isLoading = false

    var body: some View {
        ScrollView {
            VStack(spacing: 20) {
                PAXAuthHeroView(
                    style: .icon("person.badge.plus"),
                    title: String(localized: "Create account"),
                    subtitle: String(localized: "Join PAXDesign to manage projects, chat, and orders.")
                )
                .padding(.top, 8)

                VStack(spacing: 14) {
                    PAXField(title: String(localized: "Name"), icon: "person", text: $name)
                    PAXField(title: String(localized: "Email"), icon: "envelope", text: $email, keyboardType: .emailAddress)
                    PAXField(title: String(localized: "Password"), icon: "lock", text: $password, isSecure: true)
                }

                if let message {
                    Text(message)
                        .font(.footnote)
                        .foregroundStyle(message.contains("check") ? Color.secondary : Color.red)
                        .multilineTextAlignment(.center)
                        .frame(maxWidth: .infinity)
                }

                PAXPrimaryButton(
                    title: isLoading ? String(localized: "Creating…") : String(localized: "Register"),
                    isLoading: isLoading
                ) {
                    Task { await submit() }
                }
                .disabled(isLoading || email.isEmpty || password.count < 8)
            }
            .padding(.horizontal, 20)
            .padding(.bottom, 24)
        }
        .scrollDismissesKeyboard(.interactively)
        .paxScreenBackground()
        .navigationTitle(String(localized: "Register"))
        .navigationBarTitleDisplayMode(.inline)
    }

    private func submit() async {
        isLoading = true
        defer { isLoading = false }
        do {
            let response = try await api.authRegister(name: name, email: email, password: password)
            message = response.message ?? String(localized: "Check your email to verify your account.")
            onDone(email.trimmingCharacters(in: .whitespacesAndNewlines))
        } catch {
            message = error.localizedDescription
        }
    }
}

struct CustomerForgotPasswordView: View {
    @EnvironmentObject private var api: CustomerAPIClient
    var onDone: () -> Void
    @State private var email = ""
    @State private var message: String?
    @State private var isLoading = false

    var body: some View {
        ScrollView {
            VStack(spacing: 20) {
                PAXAuthHeroView(
                    style: .icon("key.fill"),
                    title: String(localized: "Reset password"),
                    subtitle: String(localized: "We will email you a secure reset link.")
                )
                .padding(.top, 8)

                PAXField(title: String(localized: "Email"), icon: "envelope", text: $email, keyboardType: .emailAddress)

                if let message {
                    Text(message)
                        .font(.footnote)
                        .foregroundStyle(.secondary)
                        .multilineTextAlignment(.center)
                        .frame(maxWidth: .infinity)
                }

                PAXPrimaryButton(
                    title: isLoading ? String(localized: "Sending…") : String(localized: "Send reset link"),
                    isLoading: isLoading
                ) {
                    Task { await submit() }
                }
                .disabled(isLoading || email.isEmpty)
            }
            .padding(.horizontal, 20)
            .padding(.bottom, 24)
        }
        .scrollDismissesKeyboard(.interactively)
        .paxScreenBackground()
        .navigationTitle(String(localized: "Forgot password"))
        .navigationBarTitleDisplayMode(.inline)
    }

    private func submit() async {
        isLoading = true
        defer { isLoading = false }
        do {
            let response = try await api.authForgotPassword(email: email)
            message = response.message ?? String(localized: "If an account exists, a reset link was sent.")
            onDone()
        } catch {
            message = error.localizedDescription
        }
    }
}

struct CustomerVerifyEmailView: View {
    @EnvironmentObject private var api: CustomerAPIClient
    var email: String = ""
    var onDone: () -> Void
    @State private var emailField = ""
    @State private var code = ""
    @State private var message: String?
    @State private var isSuccess = false
    @State private var isLoading = false
    @State private var isResending = false

    private let codeExpiryHours = 24

    var body: some View {
        ScrollView {
            VStack(spacing: 20) {
                PAXAuthHeroView(
                    style: .icon("envelope.badge.fill"),
                    title: String(localized: "Email verification"),
                    subtitle: String(localized: "Enter the 6-digit code from your email, or open the verification link.")
                )
                .padding(.top, 8)

                VStack(spacing: 14) {
                    PAXField(title: String(localized: "Email"), icon: "envelope", text: $emailField, keyboardType: .emailAddress)
                    PAXField(title: String(localized: "Verification code"), icon: "number", text: $code, keyboardType: .numberPad)
                }

                Text(String(localized: "Codes expire after \(codeExpiryHours) hours and can only be used once."))
                    .font(.caption)
                    .foregroundStyle(.secondary)
                    .frame(maxWidth: .infinity, alignment: .leading)

                if let message {
                    Text(message)
                        .font(.footnote)
                        .foregroundStyle(isSuccess ? Color.secondary : Color.red)
                        .multilineTextAlignment(.center)
                        .frame(maxWidth: .infinity)
                }

                PAXPrimaryButton(
                    title: isLoading ? String(localized: "Verifying…") : String(localized: "Verify email"),
                    isLoading: isLoading
                ) {
                    Task { await verify() }
                }
                .disabled(isLoading || emailField.isEmpty || code.count != 6)

                Button(isResending ? String(localized: "Sending…") : String(localized: "Resend verification email")) {
                    Task { await resend() }
                }
                .font(.subheadline)
                .disabled(isResending || emailField.isEmpty)
            }
            .padding(.horizontal, 20)
            .padding(.bottom, 24)
        }
        .scrollDismissesKeyboard(.interactively)
        .paxScreenBackground()
        .navigationTitle(String(localized: "Verify email"))
        .navigationBarTitleDisplayMode(.inline)
        .onAppear {
            if emailField.isEmpty, !email.isEmpty {
                emailField = email
            }
        }
        .onChange(of: code) { newValue in
            let digits = newValue.filter(\.isNumber)
            code = String(digits.prefix(6))
        }
    }

    private func verify() async {
        isLoading = true
        defer { isLoading = false }
        do {
            _ = try await api.authVerify(email: emailField.trimmingCharacters(in: .whitespacesAndNewlines), code: code)
            isSuccess = true
            message = String(localized: "Email verified. You can sign in now.")
            onDone()
        } catch {
            isSuccess = false
            message = error.localizedDescription
        }
    }

    private func resend() async {
        isResending = true
        defer { isResending = false }
        do {
            let response = try await api.authResendVerification(email: emailField.trimmingCharacters(in: .whitespacesAndNewlines))
            isSuccess = true
            if let hours = response.expires_in_hours {
                message = response.message ?? String(localized: "Verification email sent. Code valid for \(hours) hours.")
            } else {
                message = response.message ?? String(localized: "Verification email sent.")
            }
        } catch {
            isSuccess = false
            message = error.localizedDescription
        }
    }
}

struct CustomerAccountStatusView: View {
    let title: String
    let message: String
    var onRetry: (() -> Void)?

    var body: some View {
        PAXContentUnavailableView(title, systemImage: "person.crop.circle.badge.exclamationmark", description: Text(message))
            .toolbar {
                if let onRetry {
                    ToolbarItem(placement: .primaryAction) {
                        Button(String(localized: "Try again"), action: onRetry)
                    }
                }
            }
    }
}
