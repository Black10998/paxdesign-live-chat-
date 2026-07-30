import SwiftUI
import AuthenticationServices

struct PAXSignInWithAppleButton: View {
    @Environment(\.colorScheme) private var colorScheme
    var isLoading = false
    let onCredential: (ASAuthorizationAppleIDCredential) -> Void
    let onFailure: (Error) -> Void

    var body: some View {
        SignInWithAppleButton(.signIn) { request in
            request.requestedScopes = [.fullName, .email]
        } onCompletion: { result in
            switch result {
            case .success(let authorization):
                guard let credential = authorization.credential as? ASAuthorizationAppleIDCredential else {
                    onFailure(SignInWithAppleError.invalidCredential)
                    return
                }
                onCredential(credential)
            case .failure(let error):
                if (error as NSError).code == ASAuthorizationError.canceled.rawValue {
                    return
                }
                onFailure(error)
            }
        }
        .signInWithAppleButtonStyle(colorScheme == .dark ? .white : .black)
        .frame(height: 50)
        .clipShape(RoundedRectangle(cornerRadius: 10, style: .continuous))
        .disabled(isLoading)
        .opacity(isLoading ? 0.65 : 1)
        .accessibilityLabel(String(localized: "Sign in with Apple"))
    }
}

enum SignInWithAppleError: LocalizedError {
    case invalidCredential
    case missingIdentityToken

    var errorDescription: String? {
        switch self {
        case .invalidCredential:
            return String(localized: "Apple sign-in could not be completed.")
        case .missingIdentityToken:
            return String(localized: "Apple did not return a valid identity token.")
        }
    }
}

extension ASAuthorizationAppleIDCredential {
    var identityTokenString: String? {
        guard let data = identityToken else { return nil }
        return String(decoding: data, as: UTF8.self)
    }

    var authorizationCodeString: String? {
        guard let data = authorizationCode else { return nil }
        return String(decoding: data, as: UTF8.self)
    }
}
