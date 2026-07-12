import SwiftUI

/// Apple-style monochrome device silhouettes for device management.
struct PAXDeviceGlyph: View {
    let kind: PAXDeviceKind
    var size: CGFloat = 28

    var body: some View {
        Canvas { context, canvasSize in
            let rect = CGRect(origin: .zero, size: canvasSize)
            let path = devicePath(in: rect, kind: kind)
            context.fill(path, with: .color(PAXTheme.textPrimary))
        }
        .frame(width: size, height: size)
        .accessibilityHidden(true)
    }

    private func devicePath(in rect: CGRect, kind: PAXDeviceKind) -> Path {
        switch kind {
        case .iPhone:
            return iphonePath(in: rect)
        case .iPad:
            return ipadPath(in: rect)
        case .mac:
            return macPath(in: rect)
        }
    }

    private func iphonePath(in rect: CGRect) -> Path {
        var path = Path()
        let w = rect.width
        let h = rect.height
        let body = CGRect(x: w * 0.22, y: h * 0.04, width: w * 0.56, height: h * 0.92)
        path.addRoundedRect(in: body, cornerSize: CGSize(width: w * 0.12, height: w * 0.12))
        let notch = CGRect(x: w * 0.38, y: h * 0.08, width: w * 0.24, height: h * 0.04)
        path.addRoundedRect(in: notch, cornerSize: CGSize(width: h * 0.02, height: h * 0.02))
        return path
    }

    private func ipadPath(in rect: CGRect) -> Path {
        var path = Path()
        let w = rect.width
        let h = rect.height
        let body = CGRect(x: w * 0.08, y: h * 0.1, width: w * 0.84, height: h * 0.8)
        path.addRoundedRect(in: body, cornerSize: CGSize(width: w * 0.06, height: w * 0.06))
        let camera = CGRect(x: w * 0.47, y: h * 0.13, width: w * 0.06, height: w * 0.06)
        path.addEllipse(in: camera)
        return path
    }

    private func macPath(in rect: CGRect) -> Path {
        var path = Path()
        let w = rect.width
        let h = rect.height
        let screen = CGRect(x: w * 0.08, y: h * 0.06, width: w * 0.84, height: h * 0.58)
        path.addRoundedRect(in: screen, cornerSize: CGSize(width: w * 0.04, height: w * 0.04))
        let base = CGRect(x: w * 0.18, y: h * 0.68, width: w * 0.64, height: h * 0.08)
        path.addRoundedRect(in: base, cornerSize: CGSize(width: h * 0.02, height: h * 0.02))
        let stand = CGRect(x: w * 0.44, y: h * 0.64, width: w * 0.12, height: h * 0.06)
        path.addRect(stand)
        return path
    }
}

extension PAXDeviceGlyph {
    init(machine: String, size: CGFloat = 28) {
        let lower = machine.lowercased()
        if lower.contains("ipad") {
            self.init(kind: .iPad, size: size)
        } else if lower.contains("mac") || lower == "arm64" {
            self.init(kind: .mac, size: size)
        } else {
            self.init(kind: .iPhone, size: size)
        }
    }
}
