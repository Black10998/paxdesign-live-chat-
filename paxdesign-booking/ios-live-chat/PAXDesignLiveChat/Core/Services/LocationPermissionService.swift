import Foundation
import CoreLocation

@MainActor
final class LocationPermissionService: NSObject, ObservableObject, CLLocationManagerDelegate {
    static let shared = LocationPermissionService()

    @Published private(set) var status: CLAuthorizationStatus

    private let manager: CLLocationManager
    private var authContinuation: CheckedContinuation<CLAuthorizationStatus, Never>?
    private var locationContinuation: CheckedContinuation<CLLocationCoordinate2D?, Never>?

    private override init() {
        manager = CLLocationManager()
        status = manager.authorizationStatus
        super.init()
        manager.delegate = self
        manager.desiredAccuracy = kCLLocationAccuracyBest
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
            self.authContinuation = continuation
            manager.requestWhenInUseAuthorization()
        }
    }

    func captureCurrentLocation() async -> CLLocationCoordinate2D? {
        let status = await requestWhenInUse()
        guard Self.isAuthorized(status) else { return nil }
        return await fetchCurrentLocation()
    }

    func fetchCurrentLocation() async -> CLLocationCoordinate2D? {
        return await withCheckedContinuation { continuation in
            self.locationContinuation = continuation
            manager.requestLocation()
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
            authContinuation?.resume(returning: newStatus)
            authContinuation = nil
        }
    }

    nonisolated func locationManager(_ manager: CLLocationManager, didUpdateLocations locations: [CLLocation]) {
        Task { @MainActor [weak self] in
            self?.locationContinuation?.resume(returning: locations.last?.coordinate)
            self?.locationContinuation = nil
        }
    }

    nonisolated func locationManager(_ manager: CLLocationManager, didFailWithError error: Error) {
        Task { @MainActor [weak self] in
            self?.locationContinuation?.resume(returning: nil)
            self?.locationContinuation = nil
        }
    }
}
