import CoreLocation
import MapKit
import SwiftUI

struct TeamLocationPickerSheet: View {
    @Environment(\.dismiss) private var dismiss

    @State private var region: MKCoordinateRegion
    @State private var label = ""
    @State private var isSending = false
    @State private var permissionDenied = false

    let onSend: (Double, Double, String) -> Void

    init(
        initialCoordinate: CLLocationCoordinate2D? = nil,
        onSend: @escaping (Double, Double, String) -> Void
    ) {
        let center = initialCoordinate ?? CLLocationCoordinate2D(latitude: 48.2082, longitude: 16.3738)
        _region = State(initialValue: MKCoordinateRegion(
            center: center,
            span: MKCoordinateSpan(latitudeDelta: 0.01, longitudeDelta: 0.01)
        ))
        self.onSend = onSend
    }

    var body: some View {
        NavigationStack {
            VStack(spacing: 0) {
                Map(
                    coordinateRegion: $region,
                    interactionModes: .all,
                    showsUserLocation: true
                )
                .frame(maxWidth: .infinity, maxHeight: .infinity)

                VStack(spacing: 12) {
                    TextField(L10n.TeamChatPlaceholder, text: $label)
                        .textFieldStyle(.roundedBorder)
                        .disabled(isSending)

                    Button {
                        sendSelectedLocation()
                    } label: {
                        HStack(spacing: 8) {
                            if isSending {
                                PAXInlineLoader(size: 16)
                            }
                            Text(L10n.CommonSend)
                                .font(.headline)
                        }
                        .frame(maxWidth: .infinity)
                        .padding(.vertical, 12)
                    }
                    .buttonStyle(.borderedProminent)
                    .tint(PAXTheme.textPrimary)
                    .disabled(isSending)
                }
                .padding(16)
                .background(PAXBackground())
            }
            .navigationTitle(L10n.TeamShareLocation)
            .navigationBarTitleDisplayMode(.inline)
            .toolbar {
                ToolbarItem(placement: .cancellationAction) {
                    Button(L10n.CommonCancel) { dismiss() }
                        .disabled(isSending)
                }
            }
            .alert(L10n.TeamLocationDenied, isPresented: $permissionDenied) {
                Button(L10n.CommonOK, role: .cancel) { dismiss() }
            }
            .task { await prepareMap() }
        }
    }

    private func prepareMap() async {
        let status = await LocationPermissionService.shared.requestWhenInUse()
        guard LocationPermissionService.isAuthorized(status) else {
            permissionDenied = true
            return
        }
        if let coordinate = await LocationPermissionService.shared.fetchCurrentLocation() {
            region = MKCoordinateRegion(
                center: coordinate,
                span: MKCoordinateSpan(latitudeDelta: 0.01, longitudeDelta: 0.01)
            )
        }
    }

    private func sendSelectedLocation() {
        guard !isSending else { return }
        isSending = true
        let lat = region.center.latitude
        let lng = region.center.longitude
        let trimmed = label.trimmingCharacters(in: .whitespacesAndNewlines)
        onSend(lat, lng, trimmed)
        dismiss()
    }
}
