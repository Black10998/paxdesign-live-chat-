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
        Form {
            Section(String(localized: "Create account")) {
                TextField(String(localized: "Name"), text: $name)
                TextField(String(localized: "Email"), text: $email)
                    .textInputAutocapitalization(.never)
                    .keyboardType(.emailAddress)
                SecureField(String(localized: "Password"), text: $password)
            }
            if let message {
                Section { Text(message).foregroundStyle(message.contains("check") ? PAXTheme.textSecondary : Color.red) }
            }
            Section {
                Button(isLoading ? String(localized: "Creating…") : String(localized: "Register")) {
                    Task { await submit() }
                }.disabled(isLoading || email.isEmpty || password.count < 8)
            }
        }
        .navigationTitle(String(localized: "Register"))
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
        Form {
            Section(String(localized: "Reset password")) {
                TextField(String(localized: "Email"), text: $email)
                    .textInputAutocapitalization(.never)
                    .keyboardType(.emailAddress)
            }
            if let message { Section { Text(message) } }
            Section {
                Button(isLoading ? String(localized: "Sending…") : String(localized: "Send reset link")) {
                    Task { await submit() }
                }.disabled(isLoading || email.isEmpty)
            }
        }
        .navigationTitle(String(localized: "Forgot password"))
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
        Form {
            Section(String(localized: "Email verification")) {
                Text(String(localized: "Enter the 6-digit code from your email, or open the verification link."))
                    .font(.footnote)
                    .foregroundStyle(PAXTheme.textSecondary)
                TextField(String(localized: "Email"), text: $emailField)
                    .textInputAutocapitalization(.never)
                    .keyboardType(.emailAddress)
                TextField(String(localized: "Verification code"), text: $code)
                    .textInputAutocapitalization(.never)
                    .keyboardType(.numberPad)
                    .onChange(of: code) { newValue in
                        let digits = newValue.filter(\.isNumber)
                        code = String(digits.prefix(6))
                    }
                Text(String(localized: "Codes expire after \(codeExpiryHours) hours and can only be used once."))
                    .font(.caption)
                    .foregroundStyle(PAXTheme.textSecondary)
            }
            if let message {
                Section {
                    Text(message)
                        .foregroundStyle(isSuccess ? PAXTheme.textSecondary : Color.red)
                }
            }
            Section {
                Button(isLoading ? String(localized: "Verifying…") : String(localized: "Verify email")) {
                    Task { await verify() }
                }
                .disabled(isLoading || emailField.isEmpty || code.count != 6)

                Button(isResending ? String(localized: "Sending…") : String(localized: "Resend verification email")) {
                    Task { await resend() }
                }
                .disabled(isResending || emailField.isEmpty)
            }
        }
        .navigationTitle(String(localized: "Verify email"))
        .onAppear {
            if emailField.isEmpty, !email.isEmpty {
                emailField = email
            }
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
