import XCTest

final class ShellLayoutUITests: XCTestCase {
    func testCustomerChatComposerSitsAboveTabBar() {
        let app = launchCustomerVerificationApp()
        let tabBar = app.otherElements["pax.shell.tabBar"]
        let composer = app.otherElements["pax.chat.composer"]
        XCTAssertTrue(tabBar.waitForExistence(timeout: 12))
        XCTAssertTrue(composer.waitForExistence(timeout: 12))
        assertView(composer, sitsFullyAbove: tabBar)
    }

    func testCustomerTabsKeepContentAboveTabBar() {
        let app = launchCustomerVerificationApp()
        let tabBar = app.otherElements["pax.shell.tabBar"]
        XCTAssertTrue(tabBar.waitForExistence(timeout: 12))

        for tab in ["Home", "Services", "Portfolio", "Chat", "Account"] {
            app.buttons[tab].tap()
            if tab == "Chat" {
                let composer = app.otherElements["pax.chat.composer"]
                XCTAssertTrue(composer.waitForExistence(timeout: 8))
                assertView(composer, sitsFullyAbove: tabBar)
            }
            scrollContentToEnd(in: app)
            assertPrimaryContentEndsAboveTabBar(in: app, tabBar: tabBar)
        }
    }

    func testStaffDashboardKeepsContentAboveTabBar() {
        let app = launchStaffVerificationApp()
        let tabBar = app.otherElements["pax.shell.tabBar"]
        XCTAssertTrue(tabBar.waitForExistence(timeout: 12))
        scrollContentToEnd(in: app)
        assertPrimaryContentEndsAboveTabBar(in: app, tabBar: tabBar)
    }

    private func launchCustomerVerificationApp() -> XCUIApplication {
        let app = XCUIApplication()
        app.launchArguments.append("-PAXLayoutVerifyCustomer")
        app.launch()
        return app
    }

    private func launchStaffVerificationApp() -> XCUIApplication {
        let app = XCUIApplication()
        app.launchArguments.append("-PAXLayoutVerifyStaff")
        app.launch()
        return app
    }

    private func assertView(_ upper: XCUIElement, sitsFullyAbove lower: XCUIElement, tolerance: CGFloat = 2) {
        XCTAssertTrue(upper.exists)
        XCTAssertTrue(lower.exists)
        XCTAssertLessThanOrEqual(
            upper.frame.maxY,
            lower.frame.minY + tolerance,
            "Expected upper maxY \(upper.frame.maxY) to stay above lower minY \(lower.frame.minY)"
        )
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
        }
    }
}
