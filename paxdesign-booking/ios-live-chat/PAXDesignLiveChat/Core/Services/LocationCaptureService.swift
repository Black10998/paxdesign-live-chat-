import CoreLocation
import Foundation

@MainActor
final class LocationCaptureService: ObservableObject {
    static let shared = LocationCaptureService()

    private init() {}

    func captureCurrentLocation() async -> CLLocationCoordinate2D? {
        await LocationPermissionService.shared.captureCurrentLocation()
    }
}
