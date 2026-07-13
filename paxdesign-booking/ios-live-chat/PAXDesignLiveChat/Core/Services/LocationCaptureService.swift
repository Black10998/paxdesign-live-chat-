import CoreLocation
import Foundation

@MainActor
final class LocationCaptureService: NSObject, ObservableObject, CLLocationManagerDelegate {
    static let shared = LocationCaptureService()

    private let manager = CLLocationManager()
    private var continuation: CheckedContinuation<CLLocationCoordinate2D?, Never>?

    private override init() {
        super.init()
        manager.delegate = self
        manager.desiredAccuracy = kCLLocationAccuracyBest
    }

    func captureCurrentLocation() async -> CLLocationCoordinate2D? {
        let status = await LocationPermissionService.shared.requestWhenInUse()
        guard LocationPermissionService.isAuthorized(status) else { return nil }

        return await withCheckedContinuation { continuation in
            self.continuation = continuation
            manager.requestLocation()
        }
    }

    nonisolated func locationManager(_ manager: CLLocationManager, didUpdateLocations locations: [CLLocation]) {
        Task { @MainActor in
            continuation?.resume(returning: locations.last?.coordinate)
            continuation = nil
        }
    }

    nonisolated func locationManager(_ manager: CLLocationManager, didFailWithError error: Error) {
        Task { @MainActor in
            continuation?.resume(returning: nil)
            continuation = nil
        }
    }
}
