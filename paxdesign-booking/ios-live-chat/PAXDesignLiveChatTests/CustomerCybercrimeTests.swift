import XCTest
@testable import PAXDesignLiveChat

final class CustomerCybercrimeTests: XCTestCase {
    func testDecodesReportList() throws {
        let json = """
        {
          "reports": [
            {
              "reference_id": "CCS-20260814-ABCDEF12",
              "status": "in_review",
              "status_label": "In Review",
              "is_active": true,
              "category": "phishing_fraud",
              "category_label": "Phishing / fraud",
              "urgency": "high",
              "unread_count": 1
            }
          ],
          "active": {
            "reference_id": "CCS-20260814-ABCDEF12",
            "status": "in_review",
            "is_active": true,
            "category": "phishing_fraud"
          },
          "history": []
        }
        """.data(using: .utf8)!

        let decoded = try JSONDecoder().decode(CustomerCybercrimeListResponse.self, from: json)
        XCTAssertEqual(decoded.reports.count, 1)
        XCTAssertEqual(decoded.active?.reference_id, "CCS-20260814-ABCDEF12")
        XCTAssertEqual(decoded.reports.first?.displayCategory, "Phishing / fraud")
        XCTAssertTrue(decoded.reports.first?.isOpen == true)
    }

    func testSubmitResponseFallsBackToReportReference() throws {
        let json = """
        {
          "message": "ok",
          "report": { "reference_id": "CCS-20260814-ZZZZ9999", "status": "submitted", "is_active": true }
        }
        """.data(using: .utf8)!
        let decoded = try JSONDecoder().decode(CustomerCybercrimeSubmitResponse.self, from: json)
        XCTAssertEqual(decoded.reference, "CCS-20260814-ZZZZ9999")
    }

    @MainActor
    func testCybercrimeDeepLinkOpensAccountReport() {
        let navigation = CustomerNavigationCoordinator()
        navigation.handle(deepLink: CustomerDeepLink(path: "/cybercrime-support/?ref=CCS-20260814-ABCDEF12"))
        XCTAssertEqual(navigation.selectedTab, .account)
        XCTAssertEqual(navigation.accountPath.first?.kind, .cybercrimeReport("CCS-20260814-ABCDEF12"))
    }

    @MainActor
    func testWebsiteCybercrimePathOpensPortal() {
        let navigation = CustomerNavigationCoordinator()
        navigation.handle(deepLink: CustomerDeepLink(path: "/cybercrime-support/"))
        XCTAssertEqual(navigation.selectedTab, .account)
        XCTAssertEqual(navigation.accountPath.first?.kind, .cybercrime)
    }

    @MainActor
    func testOpenCybercrimeFromHomeStaysOnHomeTab() {
        let navigation = CustomerNavigationCoordinator()
        navigation.selectedTab = .home
        navigation.openCybercrime()
        XCTAssertEqual(navigation.selectedTab, .home)
        XCTAssertEqual(navigation.homePath.first?.kind, .cybercrime)
        XCTAssertTrue(navigation.accountPath.isEmpty)
    }

    @MainActor
    func testOpenCybercrimeFromServicesStaysOnServicesTab() {
        let navigation = CustomerNavigationCoordinator()
        navigation.selectedTab = .services
        navigation.openCybercrime(reference: "CCS-20260814-ABCDEF12")
        XCTAssertEqual(navigation.selectedTab, .services)
        XCTAssertEqual(navigation.servicesPath.first?.kind, .cybercrimeReport("CCS-20260814-ABCDEF12"))
    }

    func testCatalogContainsWebsitePlatforms() {
        XCTAssertTrue(CustomerCybercrimeCatalog.platforms.contains("Binance"))
        XCTAssertTrue(CustomerCybercrimeCatalog.categories.contains(where: { $0.id == "account_takeover" }))
        XCTAssertEqual(CustomerCybercrimeCatalog.urgencyLevels.count, 4)
    }

    @MainActor
    func testSubmitFieldsMatchWebsiteIntake() {
        let draft = CustomerCybercrimeDraft()
        draft.fullName = "Jane Doe"
        draft.email = "jane@example.com"
        draft.phoneLocal = "660123456"
        draft.countryCode = "AT"
        draft.category = "account_takeover"
        draft.description = "A detailed incident description that is long enough."
        draft.selectedPlatforms = ["Gmail"]
        draft.urgency = "high"
        let fields = draft.fields(chatSessionID: "pax_customer_1")
        XCTAssertEqual(fields["source"], "ios")
        XCTAssertEqual(fields["category"], "account_takeover")
        XCTAssertEqual(fields["urgency"], "high")
        XCTAssertEqual(fields["identity_accuracy"], "1")
        XCTAssertEqual(fields["decl_truthful"], "1")
        XCTAssertEqual(fields["chat_session_id"], "pax_customer_1")
        XCTAssertTrue((fields["platforms"] ?? "").contains("Gmail"))
    }

    func testChatScrollHelperAnchorIsStable() {
        XCTAssertEqual(ChatScrollHelper.bottomAnchorId, "chat-bottom-anchor")
    }
}
