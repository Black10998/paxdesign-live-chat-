import AVFoundation

/// Distinctive live-request alert using bundled ringtone loop.
@MainActor
final class IncomingCallRingtone {
    static let shared = IncomingCallRingtone()

    func startRinging() {
        PAXNotificationSound.shared.startLiveRequestLoop()
    }

    func stopRinging() {
        PAXNotificationSound.shared.stopLiveRequestLoop()
    }
}
