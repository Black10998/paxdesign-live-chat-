import Foundation
import UIKit

@MainActor
final class CustomerDeviceSessionService {
    static let shared = CustomerDeviceSessionService()

    private var heartbeatTask: Task<Void, Never>?
    private var tokenObservationTask: Task<Void, Never>?
    private weak var api: CustomerAPIClient?
    private var lastRegisteredToken: String?

    private init() {}

    func start(api: CustomerAPIClient) {
        stop()
        self.api = api
        heartbeatTask = Task { [weak self] in
            while !Task.isCancelled {
                try? await Task.sleep(nanoseconds: 1_800_000_000_000) // 30 min
                await self?.registerIfNeeded()
            }
        }
        tokenObservationTask = Task { [weak self] in
            var lastToken = CustomerPushService.shared.deviceToken
            for await token in CustomerPushService.shared.$deviceToken.values {
                guard !Task.isCancelled else { break }
                if let token, token != lastToken {
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
        lastRegisteredToken = nil
    }

    func registerIfNeeded(force: Bool = false) async {
        guard let api else { return }
        guard let token = CustomerPushService.shared.deviceToken, !token.isEmpty else { return }
        if !force, token == lastRegisteredToken { return }

        do {
            try await api.registerPush(
                token: token,
                deviceID: PAXDeviceInfo.deviceId,
                metadata: PAXDeviceInfo.registrationPayload
            )
            lastRegisteredToken = token
        } catch {
            #if DEBUG
            print("Customer device registration failed: \(error.localizedDescription)")
            #endif
        }
    }
}
