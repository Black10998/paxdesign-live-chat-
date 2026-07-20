import XCTest

final class ShellLayoutUITests: XCTestCase {
    func testCustomerChatComposerSitsAboveTabBar() {
        let app = launchCustomerVerificationApp()
        let tabBar = app.otherElements["pax.shell.tabBar"]
        XCTAssertTrue(tabBar.waitForExistence(timeout: 15))

        app.buttons["Chat"].tap()

        let composer = app.otherElements["pax.chat.composer"]
        let messageField = app.textFields["pax.chat.messageField"]
        XCTAssertTrue(composer.waitForExistence(timeout: 10) || messageField.waitForExistence(timeout: 10))

        let composerFrame = composer.exists ? composer.frame : messageField.frame
        assertFrame(composerFrame, sitsFullyAbove: tabBar.frame)
    }

    func testCustomerTabsKeepContentAboveTabBar() {
        let app = launchCustomerVerificationApp()
        let tabBar = app.otherElements["pax.shell.tabBar"]
        XCTAssertTrue(tabBar.waitForExistence(timeout: 15))

        for tab in ["Home", "Services", "Portfolio", "Chat", "Account"] {
            app.buttons[tab].tap()
            if tab == "Chat" {
                let composer = app.otherElements["pax.chat.composer"]
                let messageField = app.textFields["pax.chat.messageField"]
                XCTAssertTrue(composer.waitForExistence(timeout: 8) || messageField.waitForExistence(timeout: 8))
                let composerFrame = composer.exists ? composer.frame : messageField.frame
                assertFrame(composerFrame, sitsFullyAbove: tabBar.frame)
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
        if app.scrollViews.firstMatch.exists {
            assertView(app.scrollViews.firstMatch, sitsFullyAbove: tabBar, tolerance: 4)
            return
        }
        if app.tables.firstMatch.exists {
            assertView(app.tables.firstMatch, sitsFullyAbove: tabBar, tolerance: 4)
            return
        }
        if app.otherElements["pax.chat.composer"].exists {
            assertView(app.otherElements["pax.chat.composer"], sitsFullyAbove: tabBar)
        } else if app.textFields["pax.chat.messageField"].exists {
            assertView(app.textFields["pax.chat.messageField"], sitsFullyAbove: tabBar)
        }
    }
}
