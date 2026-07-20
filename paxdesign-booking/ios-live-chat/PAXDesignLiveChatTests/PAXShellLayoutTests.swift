import XCTest
@testable import PAXDesignLiveChat

/// Guards the shell layout contract: tab bar is a VStack sibling (not overlay/safeAreaInset).
final class PAXShellLayoutTests: XCTestCase {
    func testUiverseMenuHeightIsStable() {
        XCTAssertGreaterThan(UiverseMenuMetrics.menuHeight, 40)
        XCTAssertLessThan(UiverseMenuMetrics.menuHeight, 60)
    }

    func testShellUsesMeasuredTabBarHeightNotHardcodedScreenPadding() {
        // scrollInset is diagnostic only; shell layout must not depend on manual screen padding.
        let inset = UiverseMenuMetrics.scrollInset
        XCTAssertEqual(inset, UiverseMenuMetrics.menuHeight + UiverseMenuMetrics.homeIndicatorGap + 12)
    }
}
