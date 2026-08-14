import SwiftUI
import PhotosUI
import UniformTypeIdentifiers
import UIKit

struct CustomerCybercrimePickedFile {
    let filename: String
    let mime: String
    let data: Data
}

struct CustomerCybercrimeFileDrop: View {
    let title: String
    let icon: String
    var allowsMultiple = true
    var onFiles: ([CustomerCybercrimePickedFile]) -> Void

    @State private var showDocumentPicker = false
    @State private var showPhotoPicker = false
    @State private var photoItems: [PhotosPickerItem] = []

    var body: some View {
        Menu {
            Button {
                showPhotoPicker = true
            } label: {
                PAXLabel(String(localized: "Photos"), icon: "photo.on.rectangle")
            }
            Button {
                showDocumentPicker = true
            } label: {
                PAXLabel(String(localized: "Files"), icon: "folder")
            }
        } label: {
            HStack(spacing: 12) {
                PAXRevolutGlyphAvatar(systemImage: icon, size: 36, tint: PAXTheme.accent)
                Text(title)
                    .font(PAXTypography.rowTitle)
                    .foregroundStyle(PAXTheme.textPrimary)
                    .lineLimit(2)
                Spacer()
                PAXIcon("plus.circle.fill", size: .row, tint: PAXTheme.accent)
            }
            .padding(14)
            .paxRevolutSurface(cornerRadius: 14, elevation: 0)
        }
        .photosPicker(
            isPresented: $showPhotoPicker,
            selection: $photoItems,
            maxSelectionCount: allowsMultiple ? 6 : 1,
            matching: .images
        )
        .onChange(of: photoItems) { items in
            Task { await loadPhotos(items) }
        }
        .sheet(isPresented: $showDocumentPicker) {
            CustomerCybercrimeDocumentPicker(allowsMultiple: allowsMultiple) { urls in
                Task { await loadDocuments(urls) }
            }
        }
    }

    private func loadPhotos(_ items: [PhotosPickerItem]) async {
        var files: [CustomerCybercrimePickedFile] = []
        for item in items {
            if let data = try? await item.loadTransferable(type: Data.self), data.count <= CustomerCybercrimeCatalog.maxFileBytes {
                files.append(.init(filename: "photo-\(UUID().uuidString.prefix(6)).jpg", mime: "image/jpeg", data: data))
            }
        }
        photoItems = []
        if !files.isEmpty {
            await MainActor.run { onFiles(files) }
        }
    }

    private func loadDocuments(_ urls: [URL]) async {
        var files: [CustomerCybercrimePickedFile] = []
        for url in urls {
            let accessed = url.startAccessingSecurityScopedResource()
            defer { if accessed { url.stopAccessingSecurityScopedResource() } }
            guard let data = try? Data(contentsOf: url), data.count <= CustomerCybercrimeCatalog.maxFileBytes else { continue }
            files.append(
                .init(
                    filename: url.lastPathComponent,
                    mime: mimeType(for: url),
                    data: data
                )
            )
        }
        if !files.isEmpty {
            await MainActor.run { onFiles(files) }
        }
    }

    private func mimeType(for url: URL) -> String {
        if let type = UTType(filenameExtension: url.pathExtension)?.preferredMIMEType {
            return type
        }
        return "application/octet-stream"
    }
}

private struct CustomerCybercrimeDocumentPicker: UIViewControllerRepresentable {
    var allowsMultiple: Bool
    var onPick: ([URL]) -> Void

    func makeUIViewController(context: Context) -> UIDocumentPickerViewController {
        let types: [UTType] = [.image, .pdf, .plainText, .commaSeparatedText, .zip, .data]
        let picker = UIDocumentPickerViewController(forOpeningContentTypes: types, asCopy: true)
        picker.allowsMultipleSelection = allowsMultiple
        picker.delegate = context.coordinator
        return picker
    }

    func updateUIViewController(_ uiViewController: UIDocumentPickerViewController, context: Context) {}
    func makeCoordinator() -> Coordinator { Coordinator(onPick: onPick) }

    final class Coordinator: NSObject, UIDocumentPickerDelegate {
        let onPick: ([URL]) -> Void
        init(onPick: @escaping ([URL]) -> Void) { self.onPick = onPick }
        func documentPicker(_ controller: UIDocumentPickerViewController, didPickDocumentsAt urls: [URL]) {
            onPick(urls)
        }
    }
}
