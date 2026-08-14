import XCTest
@testable import PAXDesignLiveChat

@MainActor
final class GitHubOAuthSessionTests: XCTestCase {
    func testStartURLUsesBackendOAuthStart() throws {
        let url = try XCTUnwrap(GitHubOAuthSession.startURL())
        XCTAssertEqual(url.scheme, "https")
        XCTAssertTrue(url.path.contains("/pdx/v1/auth/github/start"))
        let items = URLComponents(url: url, resolvingAgainstBaseURL: false)?.queryItems ?? []
        XCTAssertEqual(items.first(where: { $0.name == "platform" })?.value, "ios")
        XCTAssertFalse((url.absoluteString).contains("23161838"))
    }

    func testCallbackExtractsTicket() throws {
        let url = try XCTUnwrap(URL(string: "paxlivechat://auth/github?ticket=abc123"))
        XCTAssertEqual(try GitHubOAuthSession.ticket(from: url), "abc123")
    }

    func testCallbackSurfacesServerError() {
        let url = URL(string: "paxlivechat://auth/github?error=Denied")!
        XCTAssertThrowsError(try GitHubOAuthSession.ticket(from: url)) { error in
            guard case GitHubOAuthError.server(let message) = error else {
                return XCTFail("Expected server error")
            }
            XCTAssertEqual(message, "Denied")
        }
    }
}
