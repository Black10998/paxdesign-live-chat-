import Foundation
import UIKit

@MainActor
final class CustomerDeviceSessionService {
    static let shared = CustomerDeviceSessionService()

    private var heartbeatTask: Task<Void, Never>?
    private var tokenObservationTask: Task<Void, Never>?
    private weak var api: CustomerAPIClient?

    private init() {}

    func start(api: CustomerAPIClient) {
        stop()
        self.api = api
        heartbeatTask = Task { [weak self] in
            while !Task.isCancelled {
                await self?.registerIfNeeded()
                try? await Task.sleep(nanoseconds: 300_000_000_000)
            }
        }
        tokenObservationTask = Task { [weak self] in
            var lastToken = CustomerPushService.shared.deviceToken
            while !Task.isCancelled {
                try? await Task.sleep(nanoseconds: 5_000_000_000)
                let token = CustomerPushService.shared.deviceToken
                if token != lastToken {
                    lastToken = token
                    await self?.registerIfNeeded(force: true)
                }
            }
        }
    }

    func stop() {
        heartbeatTask?.cancel()
        heartbeatTask = nil
        tokenObservationTask?.cancel()
        tokenObservationTask = nil
        api = nil
    }

    func registerIfNeeded(force: Bool = false) async {
        guard let api else { return }
        guard let token = CustomerPushService.shared.deviceToken, !token.isEmpty else { return }
        do {
            try await api.registerPush(
                token: token,
                deviceID: PAXDeviceInfo.deviceId,
                metadata: PAXDeviceInfo.registrationPayload
            )
        } catch {
            #if DEBUG
            print("Customer device registration failed: \(error.localizedDescription)")
            #endif
        }
    }
}
