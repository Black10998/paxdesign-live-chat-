import Foundation
import CoreLocation

@MainActor
final class LocationPermissionService: NSObject, ObservableObject, CLLocationManagerDelegate {
    static let shared = LocationPermissionService()

    @Published private(set) var status: CLAuthorizationStatus

    private let manager: CLLocationManager
    private var continuation: CheckedContinuation<CLAuthorizationStatus, Never>?

    private override init() {
        manager = CLLocationManager()
        status = manager.authorizationStatus
        super.init()
        manager.delegate = self
    }

    func refreshStatus() {
        status = manager.authorizationStatus
    }

    func requestWhenInUse() async -> CLAuthorizationStatus {
        refreshStatus()
        if Self.isAuthorized(status) || status == .denied || status == .restricted {
            return status
        }
        return await withCheckedContinuation { continuation in
            self.continuation = continuation
            manager.requestWhenInUseAuthorization()
        }
    }

    static func isAuthorized(_ status: CLAuthorizationStatus) -> Bool {
        status == .authorizedWhenInUse || status == .authorizedAlways
    }

    nonisolated func locationManagerDidChangeAuthorization(_ manager: CLLocationManager) {
        Task { @MainActor [weak self] in
            guard let self else { return }
            let newStatus = manager.authorizationStatus
            status = newStatus
            continuation?.resume(returning: newStatus)
            continuation = nil
        }
    }
}
