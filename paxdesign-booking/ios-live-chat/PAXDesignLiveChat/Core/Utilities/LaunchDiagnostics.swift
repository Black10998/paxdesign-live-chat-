import Foundation
import os.log

/// Lightweight startup tracing for sideload launch debugging (Console.app / Xcode device logs).
enum LaunchDiagnostics {
    private static let log = Logger(subsystem: "at.paxdesign.livechat", category: "Launch")

    static func mark(_ phase: String) {
        log.info("Launch phase: \(phase, privacy: .public)")
    }
}
