import SwiftUI

struct LoginView: View {
    @EnvironmentObject private var auth: AuthStore
    @State private var isLoading = false
    @State private var error: String?

    var body: some View {
        NavigationStack {
            Form {
                Section {
                    HStack {
                        Spacer()
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
                        .padding(.vertical, 8)
                        Spacer()
                    }
                    .listRowBackground(Color.clear)
                }

                Section {
                    TextField(L10n.LoginWebsite, text: $auth.siteURLString)
                        .textInputAutocapitalization(.never)
                        .autocorrectionDisabled()
                        .keyboardType(.URL)
                        .textContentType(.URL)

                    TextField(L10n.LoginUsername, text: $auth.username)
                        .textInputAutocapitalization(.never)
                        .autocorrectionDisabled()
                        .keyboardType(.emailAddress)
                        .textContentType(.username)

                    SecureField(L10n.LoginAppPassword, text: $auth.appPassword)
                        .textContentType(.password)
                }

                if let error {
                    Section {
                        Text(error)
                            .font(.footnote)
                            .foregroundStyle(.red)
                            .multilineTextAlignment(.center)
                            .listRowBackground(Color.clear)
                    }
                }

                Section {
                    Button {
                        Task { await signIn() }
                    } label: {
                        HStack {
                            Spacer()
                            if isLoading {
                                ProgressView()
                            } else {
                                Text(L10n.LoginSignIn)
                                    .fontWeight(.semibold)
                            }
                            Spacer()
                        }
                    }
                    .disabled(isLoading)
                }

                Section {
                    Text(L10n.LoginHint)
                        .font(.caption)
                        .foregroundStyle(.secondary)
                        .multilineTextAlignment(.center)
                        .listRowBackground(Color.clear)

                    Link(L10n.LoginPrivacy, destination: PAXLegalLinks.privacyPolicy)
                    Link(L10n.LoginTerms, destination: PAXLegalLinks.impressum)
                }
            }
            .scrollContentBackground(.hidden)
            .background(PAXBackground())
            .navigationBarTitleDisplayMode(.inline)
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
