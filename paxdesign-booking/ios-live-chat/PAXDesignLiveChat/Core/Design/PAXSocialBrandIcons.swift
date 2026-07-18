import SwiftUI

enum PAXSocialBrand: String, CaseIterable {
    case instagram
    case facebook
    case tiktok
    case linkedIn

    var url: URL {
        switch self {
        case .instagram:
            return URL(string: "https://www.instagram.com/paxdes_webdesign?igsh=eTR2endvZTQ5ZzFt&utm_source=qr")!
        case .facebook:
            return URL(string: "https://www.facebook.com/share/1JuWezscEk/?mibextid=wwXIfr")!
        case .tiktok:
            return URL(string: "https://www.tiktok.com/@paxdesignaustria?_r=1&_t=ZN-983NfWLGpVv")!
        case .linkedIn:
            return URL(string: "https://www.linkedin.com/in/ahmad-al-khalaf-26265435a?utm_source=share&utm_campaign=share_via&utm_content=profile&utm_medium=ios_app")!
        }
    }

    var label: String {
        switch self {
        case .instagram: return "Instagram"
        case .facebook: return "Facebook"
        case .tiktok: return "TikTok"
        case .linkedIn: return "LinkedIn"
        }
    }
}

struct PAXSocialBrandIcon: View {
    let brand: PAXSocialBrand
    var size: CGFloat = 22

    var body: some View {
        brandShape
            .frame(width: size, height: size)
            .accessibilityHidden(true)
    }

    @ViewBuilder
    private var brandShape: some View {
        switch brand {
        case .instagram:
            InstagramBrandShape()
                .fill(
                    LinearGradient(
                        colors: [
                            Color(red: 0.98, green: 0.55, blue: 0.18),
                            Color(red: 0.86, green: 0.16, blue: 0.42),
                            Color(red: 0.51, green: 0.22, blue: 0.82),
                        ],
                        startPoint: .topLeading,
                        endPoint: .bottomTrailing
                    )
                )
        case .facebook:
            FacebookBrandShape()
                .fill(Color(red: 0.09, green: 0.47, blue: 0.95))
        case .tiktok:
            TikTokBrandShape()
                .fill(Color.black)
        case .linkedIn:
            LinkedInBrandShape()
                .fill(Color(red: 0, green: 0.47, blue: 0.71))
        }
    }
}

private struct InstagramBrandShape: Shape {
    func path(in rect: CGRect) -> Path {
        let s = min(rect.width, rect.height)
        let o = CGPoint(x: rect.midX - s / 2, y: rect.midY - s / 2)
        var p = Path()
        p.addRoundedRect(in: CGRect(x: o.x, y: o.y, width: s, height: s), cornerSize: CGSize(width: s * 0.24, height: s * 0.24))
        p.addEllipse(in: CGRect(x: o.x + s * 0.28, y: o.y + s * 0.28, width: s * 0.44, height: s * 0.44))
        p.addEllipse(in: CGRect(x: o.x + s * 0.72, y: o.y + s * 0.16, width: s * 0.12, height: s * 0.12))
        return p
    }
}

private struct FacebookBrandShape: Shape {
    func path(in rect: CGRect) -> Path {
        let s = min(rect.width, rect.height)
        let o = CGPoint(x: rect.midX - s / 2, y: rect.midY - s / 2)
        var p = Path()
        p.addRoundedRect(in: CGRect(x: o.x, y: o.y, width: s, height: s), cornerSize: CGSize(width: s * 0.18, height: s * 0.18))
        p.move(to: CGPoint(x: o.x + s * 0.56, y: o.y + s * 0.22))
        p.addLine(to: CGPoint(x: o.x + s * 0.56, y: o.y + s * 0.52))
        p.addLine(to: CGPoint(x: o.x + s * 0.42, y: o.y + s * 0.52))
        p.addLine(to: CGPoint(x: o.x + s * 0.42, y: o.y + s * 0.62))
        p.addLine(to: CGPoint(x: o.x + s * 0.56, y: o.y + s * 0.62))
        p.addLine(to: CGPoint(x: o.x + s * 0.56, y: o.y + s * 0.78))
        p.addLine(to: CGPoint(x: o.x + s * 0.68, y: o.y + s * 0.78))
        p.addLine(to: CGPoint(x: o.x + s * 0.68, y: o.y + s * 0.22))
        p.closeSubpath()
        return p
    }
}

private struct TikTokBrandShape: Shape {
    func path(in rect: CGRect) -> Path {
        let s = min(rect.width, rect.height)
        let o = CGPoint(x: rect.midX - s / 2, y: rect.midY - s / 2)
        var p = Path()
        p.addEllipse(in: CGRect(x: o.x + s * 0.08, y: o.y + s * 0.08, width: s * 0.84, height: s * 0.84))
        p.move(to: CGPoint(x: o.x + s * 0.46, y: o.y + s * 0.28))
        p.addCurve(
            to: CGPoint(x: o.x + s * 0.46, y: o.y + s * 0.72),
            control1: CGPoint(x: o.x + s * 0.28, y: o.y + s * 0.36),
            control2: CGPoint(x: o.x + s * 0.28, y: o.y + s * 0.64)
        )
        p.addLine(to: CGPoint(x: o.x + s * 0.58, y: o.y + s * 0.72))
        p.addCurve(
            to: CGPoint(x: o.x + s * 0.58, y: o.y + s * 0.44),
            control1: CGPoint(x: o.x + s * 0.72, y: o.y + s * 0.66),
            control2: CGPoint(x: o.x + s * 0.72, y: o.y + s * 0.5)
        )
        p.addLine(to: CGPoint(x: o.x + s * 0.68, y: o.y + s * 0.44))
        p.addLine(to: CGPoint(x: o.x + s * 0.68, y: o.y + s * 0.28))
        p.closeSubpath()
        return p
    }
}

private struct LinkedInBrandShape: Shape {
    func path(in rect: CGRect) -> Path {
        let s = min(rect.width, rect.height)
        let o = CGPoint(x: rect.midX - s / 2, y: rect.midY - s / 2)
        var p = Path()
        p.addRoundedRect(in: CGRect(x: o.x, y: o.y, width: s, height: s), cornerSize: CGSize(width: s * 0.14, height: s * 0.14))
        p.addRect(CGRect(x: o.x + s * 0.2, y: o.y + s * 0.38, width: s * 0.12, height: s * 0.4))
        p.addEllipse(in: CGRect(x: o.x + s * 0.2, y: o.y + s * 0.22, width: s * 0.12, height: s * 0.12))
        p.move(to: CGPoint(x: o.x + s * 0.42, y: o.y + s * 0.38))
        p.addLine(to: CGPoint(x: o.x + s * 0.42, y: o.y + s * 0.78))
        p.addLine(to: CGPoint(x: o.x + s * 0.54, y: o.y + s * 0.78))
        p.addLine(to: CGPoint(x: o.x + s * 0.54, y: o.y + s * 0.54))
        p.addCurve(
            to: CGPoint(x: o.x + s * 0.72, y: o.y + s * 0.38),
            control1: CGPoint(x: o.x + s * 0.54, y: o.y + s * 0.44),
            control2: CGPoint(x: o.x + s * 0.62, y: o.y + s * 0.38)
        )
        p.addLine(to: CGPoint(x: o.x + s * 0.8, y: o.y + s * 0.38))
        p.addLine(to: CGPoint(x: o.x + s * 0.8, y: o.y + s * 0.78))
        p.addLine(to: CGPoint(x: o.x + s * 0.68, y: o.y + s * 0.78))
        p.addLine(to: CGPoint(x: o.x + s * 0.68, y: o.y + s * 0.56))
        p.addCurve(
            to: CGPoint(x: o.x + s * 0.54, y: o.y + s * 0.52),
            control1: CGPoint(x: o.x + s * 0.68, y: o.y + s * 0.5),
            control2: CGPoint(x: o.x + s * 0.6, y: o.y + s * 0.52)
        )
        p.addLine(to: CGPoint(x: o.x + s * 0.42, y: o.y + s * 0.52))
        p.closeSubpath()
        return p
    }
}
