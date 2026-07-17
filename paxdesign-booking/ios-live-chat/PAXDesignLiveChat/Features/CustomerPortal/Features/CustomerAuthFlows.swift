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
                    CustomerRegisterView(onDone: { mode = .verify })
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
    var onDone: () -> Void
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
                Section { Text(message).foregroundStyle(message.contains("check") ? Color.secondary : Color.red) }
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
            onDone()
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
    var onDone: () -> Void
    @State private var token = ""
    @State private var message: String?

    var body: some View {
        Form {
            Section(String(localized: "Email verification")) {
                Text(String(localized: "Open the link in your email, or enter the verification token."))
                    .font(.footnote)
                    .foregroundStyle(.secondary)
                TextField(String(localized: "Token"), text: $token)
                    .textInputAutocapitalization(.never)
            }
            if let message { Section { Text(message) } }
            Section {
                Button(String(localized: "Verify email")) {
                    Task {
                        do {
                            _ = try await api.authVerify(token: token)
                            message = String(localized: "Email verified. You can sign in.")
                            onDone()
                        } catch {
                            message = error.localizedDescription
                        }
                    }
                }.disabled(token.isEmpty)
                Button(String(localized: "Resend verification email")) {
                    Task {
                        do {
                            let response = try await api.authResendVerification(email: "")
                            message = response.message
                        } catch {
                            message = error.localizedDescription
                        }
                    }
                }
            }
        }
        .navigationTitle(String(localized: "Verify email"))
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
