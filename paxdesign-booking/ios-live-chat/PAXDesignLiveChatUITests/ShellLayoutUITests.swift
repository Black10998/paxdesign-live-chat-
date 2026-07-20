import XCTest

final class ShellLayoutUITests: XCTestCase {
    func testCustomerChatComposerSitsAboveTabBar() {
        let app = launchCustomerVerificationApp()
        let tabBar = app.otherElements["pax.shell.tabBar"]
        XCTAssertTrue(tabBar.waitForExistence(timeout: 15))

        app.buttons["Chat"].tap()

        let composer = customerComposer(in: app)
        XCTAssertTrue(composer.waitForExistence(timeout: 10))
        assertFrame(composer.frame, sitsFullyAbove: tabBar.frame)
    }

    func testCustomerTabsKeepContentAboveTabBar() {
        let app = launchCustomerVerificationApp()
        let tabBar = app.otherElements["pax.shell.tabBar"]
        XCTAssertTrue(tabBar.waitForExistence(timeout: 15))

        for tab in ["Home", "Services", "Portfolio", "Chat", "Account"] {
            app.buttons[tab].tap()
            if tab == "Chat" {
                let composer = customerComposer(in: app)
                XCTAssertTrue(composer.waitForExistence(timeout: 8))
                assertFrame(composer.frame, sitsFullyAbove: tabBar.frame)
            }
            scrollContentToEnd(in: app)
            assertPrimaryContentEndsAboveTabBar(in: app, tabBar: tabBar)
        }
    }

    func testStaffDashboardKeepsContentAboveTabBar() {
        let app = launchStaffVerificationApp()
        let tabBar = app.otherElements["pax.shell.tabBar"]
        XCTAssertTrue(tabBar.waitForExistence(timeout: 15))
        scrollContentToEnd(in: app)
        assertPrimaryContentEndsAboveTabBar(in: app, tabBar: tabBar)
    }

    private func launchCustomerVerificationApp() -> XCUIApplication {
        let app = XCUIApplication()
        app.launchEnvironment["PAX_LAYOUT_VERIFY"] = "customer"
        app.launchArguments.append("-PAXLayoutVerifyCustomer")
        app.launch()
        return app
    }

    private func launchStaffVerificationApp() -> XCUIApplication {
        let app = XCUIApplication()
        app.launchEnvironment["PAX_LAYOUT_VERIFY"] = "staff"
        app.launchArguments.append("-PAXLayoutVerifyStaff")
        app.launch()
        return app
    }

    private func customerComposer(in app: XCUIApplication) -> XCUIElement {
        let textField = app.textFields["pax.chat.composer"]
        if textField.exists { return textField }
        let textView = app.textViews["pax.chat.composer"]
        if textView.exists { return textView }
        return app.descendants(matching: .any)["pax.chat.composer"]
    }

    private func assertFrame(_ upper: CGRect, sitsFullyAbove lower: CGRect, tolerance: CGFloat = 2) {
        XCTAssertLessThanOrEqual(
            upper.maxY,
            lower.minY + tolerance,
            "Expected upper maxY \(upper.maxY) to stay above lower minY \(lower.minY)"
        )
    }

    private func assertView(_ upper: XCUIElement, sitsFullyAbove lower: XCUIElement, tolerance: CGFloat = 2) {
        XCTAssertTrue(upper.exists)
        XCTAssertTrue(lower.exists)
        assertFrame(upper.frame, sitsFullyAbove: lower.frame, tolerance: tolerance)
    }

    private func scrollContentToEnd(in app: XCUIApplication) {
        let scrollView = app.scrollViews.firstMatch
        if scrollView.exists {
            for _ in 0..<10 { scrollView.swipeUp() }
            return
        }
        let table = app.tables.firstMatch
        if table.exists {
            for _ in 0..<10 { table.swipeUp() }
        }
    }

    private func assertPrimaryContentEndsAboveTabBar(in app: XCUIApplication, tabBar: XCUIElement) {
        let content = app.otherElements["pax.shell.content"]
        if content.waitForExistence(timeout: 3) {
            assertView(content, sitsFullyAbove: tabBar, tolerance: 4)
            return
        }
        if app.otherElements["pax.chat.composer"].exists {
            assertView(app.otherElements["pax.chat.composer"], sitsFullyAbove: tabBar)
            return
        }
        let composer = customerComposer(in: app)
        if composer.exists {
            assertView(composer, sitsFullyAbove: tabBar)
        }
    }
}
