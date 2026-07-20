#!/usr/bin/env python3
"""Generate PAXIcons SVG asset catalog from Heroicons-style path data."""

from __future__ import annotations

import json
import os
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
OUT = ROOT / "PAXDesignLiveChat/Resources/Assets.xcassets/PAXIcons"

# Heroicons solid 24x24 paths (MIT). Values are list of {d, fillRule?}.
ICONS: dict[str, list[dict[str, str]]] = {
    "dashboard": [
        {"d": "M11.47 3.841a.75.75 0 0 1 1.06 0l8.69 8.69a.75.75 0 1 0 1.06-1.061l-8.689-8.69a2.25 2.25 0 0 0-3.182 0l-8.69 8.69a.75.75 0 1 0 1.061 1.06l8.69-8.689Z"},
        {"d": "m12 5.432 8.159 8.159c.03.03.06.058.091.086v6.198c0 1.035-.84 1.875-1.875 1.875H15a.75.75 0 0 1-.75-.75v-4.5a.75.75 0 0 0-.75-.75h-3a.75.75 0 0 0-.75.75V21a.75.75 0 0 1-.75.75H5.625a1.875 1.875 0 0 1-1.875-1.875v-6.198a2.29 2.29 0 0 0 .091-.086L12 5.432Z"},
    ],
    "chats": [
        {"d": "M4.91307 2.65823C6.9877 2.38888 9.10296 2.25 11.2503 2.25C13.3974 2.25 15.5124 2.38885 17.5869 2.65815C19.5091 2.90769 20.8783 4.51937 20.9923 6.38495C20.6665 6.27614 20.3212 6.20396 19.96 6.17399C18.5715 6.05874 17.1673 6 15.75 6C14.3326 6 12.9285 6.05874 11.54 6.17398C9.1817 6.36971 7.5 8.36467 7.5 10.6082V14.8937C7.5 16.5844 8.45468 18.1326 9.9328 18.8779L7.28033 21.5303C7.06583 21.7448 6.74324 21.809 6.46299 21.6929C6.18273 21.5768 6 21.3033 6 21V16.9705C5.63649 16.9316 5.27417 16.8887 4.91308 16.8418C2.90466 16.581 1.5 14.8333 1.5 12.8626V6.63738C1.5 4.66672 2.90466 2.91899 4.91307 2.65823Z"},
        {"d": "M15.75 7.5C14.3741 7.5 13.0114 7.55702 11.6641 7.66884C10.1248 7.7966 9 9.10282 9 10.6082V14.8937C9 16.4014 10.128 17.7083 11.6692 17.8341C12.9131 17.9357 14.17 17.9912 15.4384 17.999L18.2197 20.7803C18.4342 20.9948 18.7568 21.059 19.037 20.9429C19.3173 20.8268 19.5 20.5533 19.5 20.25V17.8601C19.6103 17.8518 19.7206 17.8432 19.8307 17.8342C21.372 17.7085 22.5 16.4015 22.5 14.8938V10.6082C22.5 9.10283 21.3752 7.79661 19.836 7.66885C18.4886 7.55702 17.1259 7.5 15.75 7.5Z"},
    ],
    "team": [
        {"d": "M4.5 6.375C4.5 4.09683 6.34683 2.25 8.625 2.25C10.9032 2.25 12.75 4.09683 12.75 6.375C12.75 8.65317 10.9032 10.5 8.625 10.5C6.34683 10.5 4.5 8.65317 4.5 6.375Z"},
        {"d": "M14.25 8.625C14.25 6.76104 15.761 5.25 17.625 5.25C19.489 5.25 21 6.76104 21 8.625C21 10.489 19.489 12 17.625 12C15.761 12 14.25 10.489 14.25 8.625Z"},
        {"d": "M1.5 19.125C1.5 15.19 4.68997 12 8.625 12C12.56 12 15.75 15.19 15.75 19.125V19.1276C15.75 19.1674 15.7496 19.2074 15.749 19.2469C15.7446 19.5054 15.6074 19.7435 15.3859 19.8768C13.4107 21.0661 11.0966 21.75 8.625 21.75C6.15343 21.75 3.8393 21.0661 1.86406 19.8768C1.64256 19.7435 1.50537 19.5054 1.50103 19.2469C1.50034 19.2064 1.5 19.1657 1.5 19.125Z"},
        {"d": "M17.2498 19.1281C17.2498 19.1762 17.2494 19.2244 17.2486 19.2722C17.2429 19.6108 17.1612 19.9378 17.0157 20.232C17.2172 20.2439 17.4203 20.25 17.6248 20.25C19.2206 20.25 20.732 19.8803 22.0764 19.2213C22.3234 19.1002 22.4843 18.8536 22.4957 18.5787C22.4984 18.5111 22.4998 18.4432 22.4998 18.375C22.4998 15.6826 20.3172 13.5 17.6248 13.5C16.8784 13.5 16.1711 13.6678 15.5387 13.9676C16.6135 15.4061 17.2498 17.1912 17.2498 19.125V19.1281Z"},
    ],
    "live": [
        {"d": "M5.25001 8.9998C5.25012 5.27197 8.27215 2.25 12 2.25C15.7279 2.25 18.75 5.27208 18.75 9L18.7498 9.04919V9.75C18.7498 11.8731 19.5508 13.8074 20.8684 15.2699C21.0349 15.4547 21.0989 15.71 21.0393 15.9516C20.9797 16.1931 20.8042 16.3893 20.5709 16.4755C19.0269 17.0455 17.4105 17.4659 15.7396 17.7192C15.7465 17.812 15.75 17.9056 15.75 18C15.75 20.0711 14.0711 21.75 12 21.75C9.92894 21.75 8.25001 20.0711 8.25001 18C8.25001 17.9056 8.25351 17.812 8.2604 17.7192C6.58934 17.4659 4.97287 17.0455 3.42875 16.4755C3.19539 16.3893 3.01992 16.1931 2.96033 15.9516C2.90073 15.71 2.96476 15.4547 3.13126 15.2699C4.44879 13.8074 5.24981 11.8731 5.24981 9.75L5.25001 8.9998ZM9.75221 17.8993C9.75075 17.9326 9.75001 17.9662 9.75001 18C9.75001 19.2426 10.7574 20.25 12 20.25C13.2427 20.25 14.25 19.2426 14.25 18C14.25 17.9662 14.2493 17.9326 14.2478 17.8992C13.5072 17.9659 12.7574 18 11.9998 18C11.2424 18 10.4927 17.966 9.75221 17.8993Z", "fillRule": "evenodd"},
    ],
    "platform": [
        {"d": "M4 4a2 2 0 0 0-2 2v4a2 2 0 0 0 2 2h4a2 2 0 0 0 2-2V6a2 2 0 0 0-2-2H4Z", "fillRule": "evenodd"},
        {"d": "M14 4a2 2 0 0 0-2 2v4a2 2 0 0 0 2 2h4a2 2 0 0 0 2-2V6a2 2 0 0 0-2-2h-4Z", "fillRule": "evenodd"},
        {"d": "M4 14a2 2 0 0 0-2 2v4a2 2 0 0 0 2 2h4a2 2 0 0 0 2-2v-4a2 2 0 0 0-2-2H4Z", "fillRule": "evenodd"},
        {"d": "M14 14a2 2 0 0 0-2 2v4a2 2 0 0 0 2 2h4a2 2 0 0 0 2-2v-4a2 2 0 0 0-2-2h-4Z", "fillRule": "evenodd"},
    ],
    "search": [{"d": "M10.5 3.75a6.75 6.75 0 1 0 0 13.5 6.75 6.75 0 0 0 0-13.5ZM2.25 10.5a8.25 8.25 0 1 1 14.59 5.28l4.69 4.69a.75.75 0 1 1-1.06 1.06l-4.69-4.69A8.25 8.25 0 0 1 2.25 10.5Z", "fillRule": "evenodd"}],
    "gear": [{"d": "M17.004 10.407c.138.435-.216.842-.672.842h-3.465a.75.75 0 0 1-.65-.375l-1.732-3c-.229-.396-.053-.907.393-1.004a5.252 5.252 0 0 1 6.126 3.537ZM8.12 8.464c.307-.338.838-.235 1.066.16l1.732 3a.75.75 0 0 1 0 .75l-1.732 3c-.229.397-.76.5-1.067.161A5.23 5.23 0 0 1 6.75 12a5.23 5.23 0 0 1 1.37-3.536ZM10.878 17.13c-.447-.098-.623-.608-.394-1.004l1.733-3.002a.75.75 0 0 1 .65-.375h3.465c.457 0 .81.407.672.842a5.252 5.252 0 0 1-6.126 3.539Z"}, {"d": "M21 12.75a.75.75 0 1 0 0-1.5h-.783a8.22 8.22 0 0 0-.237-1.357l.734-.267a.75.75 0 1 0-.513-1.41l-.735.268a8.24 8.24 0 0 0-.689-1.192l.6-.503a.75.75 0 1 0-.964-1.149l-.6.504a8.3 8.3 0 0 0-1.054-.885l.391-.678a.75.75 0 1 0-1.299-.75l-.39.676a8.188 8.188 0 0 0-1.295-.47l.136-.77a.75.75 0 0 0-1.477-.26l-.136.77a8.36 8.36 0 0 0-1.377 0l-.136-.77a.75.75 0 1 0-1.477.26l.136.77c-.448.121-.88.28-1.294.47l-.39-.676a.75.75 0 0 0-1.3.75l.392.678a8.29 8.29 0 0 0-1.054.885l-.6-.504a.75.75 0 1 0-.965 1.149l.6.503c.261-.375.492-.774.69-1.191l.735.267a.75.75 0 1 0 .512-1.41l-.734-.267c.115-.439.195-.892.237-1.356h.784Zm-2.657-3.06a6.744 6.744 0 0 0-1.19-2.053 6.784 6.784 0 0 0-1.82-1.51A6.705 6.705 0 0 0 12 5.25a6.8 6.8 0 0 0-1.225.11 6.7 6.7 0 0 0-2.15.793 6.784 6.784 0 0 0-2.952 3.489.76.76 0 0 1-.036.098A6.74 6.74 0 0 0 5.251 12a6.74 6.74 0 0 0 3.366 5.842l.009.005a6.704 6.704 0 0 0 2.18.798l.022.003a6.792 6.792 0 0 0 2.368-.004 6.704 6.704 0 0 0 2.205-.811 6.785 6.785 0 0 0 1.762-1.484l.009-.01.009-.01a6.743 6.743 0 0 0 1.18-2.066c.253-.707.39-1.469.39-2.263a6.74 6.74 0 0 0-.408-2.309Z", "fillRule": "evenodd"}],
    "notification": [{"d": "M5.25 8.9998C5.25012 5.27197 8.27215 2.25 12 2.25C15.7279 2.25 18.75 5.27208 18.75 9L18.7498 9.04919V9.75C18.7498 11.8731 19.5508 13.8074 20.8684 15.2699C21.0349 15.4547 21.0989 15.71 21.0393 15.9516C20.9797 16.1931 20.8042 16.3893 20.5709 16.4755C19.0269 17.0455 17.4105 17.4659 15.7396 17.7192C15.7465 17.812 15.75 17.9056 15.75 18C15.75 20.0711 14.0711 21.75 12 21.75C9.92894 21.75 8.25001 20.0711 8.25001 18C8.25001 17.9056 8.25351 17.812 8.2604 17.7192C6.58934 17.4659 4.97287 17.0455 3.42875 16.4755C3.19539 16.3893 3.01992 16.1931 2.96033 15.9516C2.90073 15.71 2.96476 15.4547 3.13126 15.2699C4.44879 13.8074 5.24981 11.8731 5.24981 9.75L5.25 8.9998Z", "fillRule": "evenodd"}],
    "calendar": [{"d": "M6.75 2.25A.75.75 0 0 1 7.5 3v1.5h9V3A.75.75 0 0 1 18 3v1.5h.75a3 3 0 0 1 3 3v11.25a3 3 0 0 1-3 3H5.25a3 3 0 0 1-3-3V7.5a3 3 0 0 1 3-3H6V3a.75.75 0 0 1 .75-.75Zm13.5 9a1.5 1.5 0 0 0-1.5-1.5H5.25a1.5 1.5 0 0 0-1.5 1.5v7.5a1.5 1.5 0 0 0 1.5 1.5h13.5a1.5 1.5 0 0 0 1.5-1.5v-7.5Z", "fillRule": "evenodd"}],
    "checklist": [{"d": "M8.25 3.75a.75.75 0 0 0-1.5 0v3.16l-.704 1.704a.75.75 0 0 0 .557.957l2.026.506a.75.75 0 0 0 .928-.557l.704-1.704V3.75Zm6 0a.75.75 0 0 0-1.5 0v3.16l-.704 1.704a.75.75 0 0 0 .557.957l2.026.506a.75.75 0 0 0 .928-.557l.704-1.704V3.75Zm-9 6.75a.75.75 0 0 0-1.5 0v3.16l-.704 1.704a.75.75 0 0 0 .557.957l2.026.506a.75.75 0 0 0 .928-.557l.704-1.704v-3.16Zm6 0a.75.75 0 0 0-1.5 0v3.16l-.704 1.704a.75.75 0 0 0 .557.957l2.026.506a.75.75 0 0 0 .928-.557l.704-1.704v-3.16Zm6 0a.75.75 0 0 0-1.5 0v3.16l-.704 1.704a.75.75 0 0 0 .557.957l2.026.506a.75.75 0 0 0 .928-.557l.704-1.704v-3.16Z"}],
    "envelope": [{"d": "M1.5 8.67v8.58a3 3 0 0 0 3 3h15a3 3 0 0 0 3-3V8.67l-8.928 5.493a3 3 0 0 1-3.144 0L1.5 8.67Z"}, {"d": "M22.5 6.908V6.75a3 3 0 0 0-3-3h-15a3 3 0 0 0-3 3v.158l9.714 5.978a1.5 1.5 0 0 0 1.572 0L22.5 6.908Z"}],
    "chart.bar": [{"d": "M18.375 2.25c-1.035 0-1.875.84-1.875 1.875v15.75c0 1.035.84 1.875 1.875 1.875h.75c1.035 0 1.875-.84 1.875-1.875V4.125c0-1.036-.84-1.875-1.875-1.875h-.75ZM9.75 8.625c0-1.036.84-1.875 1.875-1.875h.75c1.036 0 1.875.84 1.875 1.875v11.25c0 1.035-.84 1.875-1.875 1.875h-.75a1.875 1.875 0 0 1-1.875-1.875V8.625ZM3 13.125c0-1.036.84-1.875 1.875-1.875h.75c1.036 0 1.875.84 1.875 1.875v6.75c0 1.035-.84 1.875-1.875 1.875h-.75A1.875 1.875 0 0 1 3 19.875v-6.75Z"}],
    "plus": [{"d": "M12 4.5a.75.75 0 0 1 .75.75v6h6a.75.75 0 0 1 0 1.5h-6v6a.75.75 0 0 1-1.5 0v-6h-6a.75.75 0 0 1 0-1.5h6v-6A.75.75 0 0 1 12 4.5Z"}],
    "trash": [{"d": "M16.5 4.478v.227a48.816 48.816 0 0 1 3.878.512.75.75 0 1 1-.256 1.478l-.209-.035-1.005 13.07a3 3 0 0 1-2.991 2.77H8.084a3 3 0 0 1-2.991-2.77L4.087 6.66l-.209.035a.75.75 0 0 1-.256-1.478A48.567 48.567 0 0 1 7.5 4.705v-.227c0-1.564 1.213-2.9 2.816-2.951a52.662 52.662 0 0 1 3.369 0c1.603.051 2.815 1.387 2.815 2.951Zm-6.136-1.452a51.196 51.196 0 0 1 3.273 0C14.39 3.05 15 3.684 15 4.478v.113a49.488 49.488 0 0 0-6 0v-.113c0-.794.609-1.428 1.364-1.452Zm-.355 5.945a.75.75 0 1 0-1.5.058l.347 9.086a.75.75 0 1 0 1.499-.058l-.346-9.086Zm1.974.058a.75.75 0 1 0-1.498-.058l-.347 9.086a.75.75 0 0 0 1.5.058l.345-9.086Z"}],
    "archivebox": [{"d": "M3.375 3C2.339 3 1.5 3.84 1.5 4.875v.75c0 1.036.84 1.875 1.875 1.875h17.25c1.035 0 1.875-.84 1.875-1.875v-.75C22.5 3.839 21.66 3 20.625 3H3.375Z"}, {"d": "M3.087 9l.54 9.176A3 3 0 0 0 6.62 21.25H17.38a3 3 0 0 0 2.993-3.074L20.913 9H3.087Zm6.133 2.845a.75.75 0 0 1 1.06 0l2.25 2.25a.75.75 0 1 1-1.06 1.06l-1.47-1.47-1.47 1.47a.75.75 0 0 1-1.06-1.06l2.25-2.25Z"}],
    "chevron.right": [{"d": "M16.28 11.47a.75.75 0 0 0 0-1.06l-7.5-7.5a.75.75 0 0 0-1.06 1.06L14.69 12l-6.97 6.97a.75.75 0 1 0 1.06 1.06l7.5-7.5Z", "fillRule": "evenodd"}],
    "lock": [{"d": "M18 1.5c2.9 0 5.25 2.35 5.25 5.25v3.75a.75.75 0 0 1-1.5 0V6.75a3.75 3.75 0 1 0-7.5 0v3.75a.75.75 0 0 1-1.5 0V6.75C9.75 3.85 12.1 1.5 15 1.5Z"}, {"d": "M5.133 19.5h13.734c1.036 0 1.875-.84 1.875-1.875V9.75c0-1.036-.84-1.875-1.875-1.875H5.133c-1.036 0-1.875.84-1.875 1.875v7.875c0 1.036.84 1.875 1.875 1.875Z"}],
    "profile.user": [{"d": "M7.5 6a4.5 4.5 0 1 1 9 0 4.5 4.5 0 0 1-9 0ZM3.751 20.105a8.25 8.25 0 0 1 16.498 0 .75.75 0 0 1-.437.695A18.683 18.683 0 0 1 12 22.5c-2.786 0-5.433-.608-7.812-1.7a.75.75 0 0 1-.437-.695Z", "fillRule": "evenodd"}],
    "paperplane": [{"d": "M3.478 2.404a.75.75 0 0 0-.926.941l2.432 7.905H13.5a.75.75 0 0 1 0 1.5H4.984l-2.432 7.905a.75.75 0 0 0 .926.94 60.519 60.519 0 0 0 18.445-8.986.75.75 0 0 0 0-1.218A60.517 60.517 0 0 0 3.478 2.404Z"}],
    "sparkles": [{"d": "M9 4.5a.75.75 0 0 1 .721.544l.813 2.846a3.75 3.75 0 0 0 2.576 2.576l2.846.813a.75.75 0 0 1 0 1.442l-2.846.813a3.75 3.75 0 0 0-2.576 2.576l-.813 2.846a.75.75 0 0 1-1.442 0l-.813-2.846a3.75 3.75 0 0 0-2.576-2.576l-2.846-.813a.75.75 0 0 1 0-1.442l2.846-.813A3.75 3.75 0 0 0 7.466 7.89l.813-2.846A.75.75 0 0 1 9 4.5ZM18 1.5a.75.75 0 0 1 .728.568l.258 1.036c.236.94.97 1.674 1.91 1.91l1.036.258a.75.75 0 0 1 0 1.456l-1.036.258c-.94.236-1.674.97-1.91 1.91l-.258 1.036a.75.75 0 0 1-1.456 0l-.258-1.036a2.625 2.625 0 0 0-1.91-1.91l-1.036-.258a.75.75 0 0 1 0-1.456l1.036-.258a2.625 2.625 0 0 0 1.91-1.91l.258-1.036A.75.75 0 0 1 18 1.5ZM16.5 15a.75.75 0 0 1 .712.513l.394 1.183c.15.447.5.799.948.948l1.183.395a.75.75 0 0 1 0 1.422l-1.183.395c-.447.15-.799.5-.948.948l-.395 1.183a.75.75 0 0 1-1.422 0l-.395-1.183a1.5 1.5 0 0 0-.948-.948l-1.183-.395a.75.75 0 0 1 0-1.422l1.183-.395c.447-.15.799-.5.948-.948l.395-1.183A.75.75 0 0 1 16.5 15Z"}],
    "folder": [{"d": "M19.5 21a3 3 0 0 0 3-3v-4.5a3 3 0 0 0-3-3h-15a3 3 0 0 0-3 3V18a3 3 0 0 0 3 3h15ZM1.5 10.146V6a3 3 0 0 1 3-3h5.379a2.25 2.25 0 0 1 1.59.659l2.122 2.121c.14.141.331.22.53.22H19.5a3 3 0 0 1 3 3v1.146A4.483 4.483 0 0 0 19.5 9h-15a4.483 4.483 0 0 0-3 1.146Z"}],
    "photo": [{"d": "M1.5 6a2.25 2.25 0 0 1 2.25-2.25h16.5A2.25 2.25 0 0 1 22.5 6v12a2.25 2.25 0 0 1-2.25 2.25H3.75A2.25 2.25 0 0 1 1.5 18V6ZM3.75 7.5a.75.75 0 0 0-.75.75v9.75c0 .414.336.75.75.75h16.5a.75.75 0 0 0 .75-.75V8.25a.75.75 0 0 0-.75-.75H3.75Z", "fillRule": "evenodd"}, {"d": "M6.75 10.5a2.25 2.25 0 1 0 0-4.5 2.25 2.25 0 0 0 0 4.5ZM2.25 18l6.364-6.364a1.125 1.125 0 0 1 1.591 0L15.75 16.5l-2.121-2.121a1.125 1.125 0 0 0-1.591 0L2.25 18Z"}],
    "location": [{"d": "M11.47 3.841a.75.75 0 0 1 1.06 0l8.69 8.69a.75.75 0 1 0 1.06-1.061l-8.689-8.69a2.25 2.25 0 0 0-3.182 0l-8.69 8.69a.75.75 0 1 0 1.061 1.06l8.69-8.689Z"}, {"d": "m12 5.432 8.159 8.159c.03.03.06.058.091.086v6.198c0 1.035-.84 1.875-1.875 1.875H15a.75.75 0 0 1-.75-.75v-4.5a.75.75 0 0 0-.75-.75h-3a.75.75 0 0 0-.75.75V21a.75.75 0 0 1-.75.75H5.625a1.875 1.875 0 0 1-1.875-1.875v-6.198a2.29 2.29 0 0 0 .091-.086L12 5.432Z"}],
    "mic": [{"d": "M8.25 4.5a3.75 3.75 0 0 1 7.5 0v8.25a3.75 3.75 0 0 1-7.5 0V4.5Z"}, {"d": "M6 10.5a.75.75 0 0 1 .75.75v1.5a5.25 5.25 0 1 0 10.5 0v-1.5a.75.75 0 0 1 1.5 0v1.5a6.751 6.751 0 0 1-6 6.709v2.291h3a.75.75 0 0 1 0 1.5h-7.5a.75.75 0 0 1 0-1.5h3v-2.291a6.751 6.751 0 0 1-6-6.709v-1.5A.75.75 0 0 1 6 10.5Z"}],
    "ellipsis.circle": [{"d": "M12 6.75a.75.75 0 1 1 0-1.5.75.75 0 0 1 0 1.5ZM12 12.75a.75.75 0 1 1 0-1.5.75.75 0 0 1 0 1.5ZM12 18.75a.75.75 0 1 1 0-1.5.75.75 0 0 1 0 1.5Z"}],
    "pin": [{"d": "M16.5 12.75a1.5 1.5 0 0 0-1.5-1.5h-1.5v-1.5a4.5 4.5 0 1 0-9 0v1.5H3.75a1.5 1.5 0 0 0-1.5 1.5v3.75a1.5 1.5 0 0 0 1.5 1.5h13.5a1.5 1.5 0 0 0 1.5-1.5v-3.75Z"}],
    "eye.slash": [{"d": "M3.98 8.223A10.477 10.477 0 0 0 1.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.451 10.451 0 0 1 12 4.5c4.756 0 8.773 3.162 10.065 7.498a10.522 10.522 0 0 1-4.293 5.774M6.228 6.228 3 3m3.228 3.228 3.65 3.65m7.894 7.894L21 21m-3.228-3.228-3.65-3.65m0 0a3 3 0 1 0-4.243-4.243m4.242 4.242L9.88 9.88"}],
    "xmark.circle": [{"d": "M12 2.25c-5.385 0-9.75 4.365-9.75 9.75s4.365 9.75 9.75 9.75 9.75-4.365 9.75-9.75S17.385 2.25 12 2.25Zm-1.72 6.97a.75.75 0 1 0-1.06 1.06L10.94 12l-1.72 1.72a.75.75 0 1 0 1.06 1.06L12 13.06l1.72 1.72a.75.75 0 1 0 1.06-1.06L13.06 12l1.72-1.72a.75.75 0 1 0-1.06-1.06L12 10.94l-1.72-1.72Z", "fillRule": "evenodd"}],
    "checkmark.circle": [{"d": "M2.25 12c0-5.385 4.365-9.75 9.75-9.75s9.75 4.365 9.75 9.75-4.365 9.75-9.75 9.75S2.25 17.385 2.25 12Zm13.36-1.814a.75.75 0 1 0-1.22-.872l-3.236 4.53L9.53 12.22a.75.75 0 0 0-1.06 1.06l2.25 2.25a.75.75 0 0 0 1.14-.094l3.75-5.25Z", "fillRule": "evenodd"}],
    "slider.horizontal.3": [{"d": "M10.5 6h9.75M10.5 6a1.5 1.5 0 1 1-3 0m3 0a1.5 1.5 0 1 0-3 0M3.75 6H7.5m3 12h9.75m-9.75 0a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m-3.75 0H7.5m9-6h3.75m-3.75 0a1.5 1.5 0 0 1-3 0m3 0a1.5 1.5 0 0 0-3 0m-9.75 0h9.75"}],
    "admin.shield": [{"d": "M12 1.5a5.25 5.25 0 0 0-5.25 5.25v3a3 3 0 0 0-3 3v6.75a3 3 0 0 0 3 3h10.5a3 3 0 0 0 3-3v-6.75a3 3 0 0 0-3-3v-3c0-2.9-2.35-5.25-5.25-5.25Zm3.75 8.25v6.75a.75.75 0 0 1-1.5 0v-6.75a.75.75 0 0 1 1.5 0ZM9 12.75a.75.75 0 0 0-1.5 0v6.75a.75.75 0 0 0 1.5 0v-6.75Z", "fillRule": "evenodd"}],
    "doc.text": [{"d": "M5.625 1.5H9a3.75 3.75 0 0 1 3.75 3.75v1.875c0 1.036.84 1.875 1.875 1.875H16.5a3.75 3.75 0 0 1 3.75 3.75v7.875c0 1.035-.84 1.875-1.875 1.875H5.625a1.875 1.875 0 0 1-1.875-1.875V3.375c0-1.036.84-1.875 1.875-1.875Zm5.29 11.122a.75.75 0 1 0-1.5 0v2.25a.75.75 0 0 0 1.5 0v-2.25Zm-1.958 1.875h2.25a.75.75 0 0 0 0-1.5h-2.25a.75.75 0 0 0 0 1.5ZM4.125 5.25c-1.036 0-1.875.84-1.875 1.875v12.75c0 1.035.84 1.875 1.875 1.875h12.75c1.035 0 1.875-.84 1.875-1.875V11.25a9 9 0 0 0-9-9h-.375a1.875 1.875 0 0 1-1.875-1.875V5.25Z"}],
    "globe": [{"d": "M12 2.25c-5.385 0-9.75 4.365-9.75 9.75s4.365 9.75 9.75 9.75 9.75-4.365 9.75-9.75S17.385 2.25 12 2.25ZM8.547 4.505a8.969 8.969 0 0 0-3.168 4.762.75.75 0 1 1-1.449-.39 10.469 10.469 0 0 1 3.699-5.562.75.75 0 1 1 .918 1.19Zm11.906 4.762a.75.75 0 1 1-1.449.39 8.968 8.968 0 0 0-3.168-4.762.75.75 0 1 1 .918-1.19 10.469 10.469 0 0 1 3.699 5.562Zm-7.406 10.617a8.969 8.969 0 0 0 3.168-4.762.75.75 0 1 1 1.449-.39 10.469 10.469 0 0 1-3.699 5.562.75.75 0 1 1-.918-1.19ZM4.505 15.453a8.969 8.969 0 0 0 3.168 4.762.75.75 0 1 1-.918 1.19 10.469 10.469 0 0 1-3.699-5.562.75.75 0 1 1 1.449.39Z"}],
}

# Aliases for all catalog glyphs
ALIASES: dict[str, str] = {
    "dashboard.fill": "dashboard",
    "chats.fill": "chats",
    "chat.bubble": "chats",
    "team.fill": "team",
    "live.fill": "live",
    "platform.fill": "platform",
    "message.fill": "paperplane",
    "paperplane.fill": "paperplane",
    "envelope.badge": "notification",
    "envelope.open": "envelope",
    "envelope.open.fill": "envelope",
    "envelope.fill": "envelope",
    "bell.badge.fill": "notification",
    "bell.and.waves.left.and.right": "live",
    "bell.and.waves.left.and.right.fill": "live",
    "bell.fill": "live",
    "bell.badge": "notification",
    "house": "dashboard",
    "house.fill": "dashboard",
    "square.grid.2x2": "platform",
    "square.grid.2x2.fill": "platform",
    "person.3": "team",
    "person.3.fill": "team",
    "person.3.sequence": "team",
    "person.3.sequence.fill": "team",
    "person.2": "team",
    "person.2.badge.gearshape": "team",
    "person.2.wave.2": "team",
    "person.wave.2": "team",
    "bubble.left.and.bubble.right": "chats",
    "bubble.left.and.bubble.right.fill": "chats",
    "bubble.left": "chats",
    "chart.bar.doc.horizontal": "chart.bar",
    "chart.bar.doc.horizontal.fill": "chart.bar",
    "chart.line": "chart.bar",
    "chart.xyaxis.line": "chart.bar",
    "clock.history": "calendar",
    "clock.arrow.circlepath": "calendar",
    "calendar.badge.clock": "calendar",
    "checklist": "checklist",
    "checkmark": "checkmark.circle",
    "checkmark.circle.fill": "checkmark.circle",
    "checkmark.shield": "admin.shield",
    "checkmark.seal": "checkmark.circle",
    "checkmark.seal.fill": "checkmark.circle",
    "xmark": "xmark.circle",
    "plus.circle": "plus",
    "minus.circle": "xmark.circle",
    "magnifyingglass": "search",
    "gearshape": "gear",
    "gearshape.fill": "gear",
    "lock.fill": "lock",
    "lock.shield": "admin.shield",
    "lock.shield.fill": "admin.shield",
    "shield": "admin.shield",
    "shield.checkered": "admin.shield",
    "shield.lefthalf.filled": "admin.shield",
    "shield.lefthalf.filled.badge.checkmark": "admin.shield",
    "person.crop.circle": "profile.user",
    "person.crop.circle.fill": "profile.user",
    "person.badge.key": "profile.user",
    "person.badge.key.fill": "profile.user",
    "person.badge.shield.checkmark": "admin.shield",
    "person.crop.circle.badge.checkmark": "profile.user",
    "person.crop.circle.badge.clock": "profile.user",
    "employee.badge": "profile.user",
    "arrow.up.circle.fill": "paperplane",
    "send": "paperplane",
    "square.and.pencil": "compose",
    "compose": "paperplane",
    "link": "globe",
    "link.chain": "globe",
    "link.badge.plus": "plus",
    "line.3.horizontal": "slider.horizontal.3",
    "slider.horizontal.3": "slider.horizontal.3",
    "doc.on.doc": "doc.text",
    "square.and.arrow.up": "paperplane",
    "questionmark.circle": "help.bubble",
    "questionmark.circle.fill": "help.bubble",
    "info.circle": "about.info",
    "info.circle.fill": "about.info",
    "help.bubble": "sparkles",
    "about.info": "sparkles",
    "exclamationmark.triangle": "sparkles",
    "exclamationmark.shield": "admin.shield",
    "folder.fill": "folder",
    "files.stack": "folder",
    "photo.on.rectangle": "photo",
    "camera": "photo",
    "phone": "mic",
    "phone.down": "mic",
    "phone.fill": "mic",
    "iphone": "profile.user",
    "iphone.slash": "profile.user",
    "device.phone": "profile.user",
    "faceid": "profile.user",
    "touchid": "profile.user",
    "delete.left": "chevron.right",
    "chevron.up": "chevron.right",
    "arrow.up": "chevron.right",
    "arrow.down": "chevron.right",
    "arrow.up.right": "chevron.right",
    "arrow.up.left": "chevron.right",
    "arrow.down.right": "chevron.right",
    "arrow.up.forward.app": "paperplane",
    "arrow.up.right.circle": "chevron.right",
    "star": "sparkles",
    "crown": "sparkles",
    "heart": "sparkles",
    "hand.thumbsdown": "xmark.circle",
    "hand.raised": "profile.user",
    "safari": "globe",
    "briefcase": "folder",
    "dollarsign.circle": "chart.bar",
    "number": "checklist",
    "paintbrush": "sparkles",
    "paintpalette": "sparkles",
    "speaker.wave.2": "mic",
    "lifepreserver": "admin.shield",
    "externaldrive": "folder",
    "key": "lock",
    "waveform": "mic",
    "play": "paperplane",
    "play.fill": "paperplane",
    "pause": "ellipsis.circle",
    "pause.fill": "ellipsis.circle",
    "team.headset": "team",
    "team.broadcast": "team",
    "team.alert": "notification",
    "location.fill": "location",
    "mic.fill": "mic",
}

CATALOG_GLYPHS = [
    "about.info", "admin.shield", "archivebox", "arrow.down", "arrow.down.right", "arrow.up",
    "arrow.up.forward.app", "arrow.up.left", "arrow.up.right", "arrow.up.right.circle", "briefcase",
    "calendar", "camera", "chart.bar", "chart.line", "chat.bubble", "chats", "chats.fill", "checklist",
    "checkmark.circle", "checkmark.seal", "checkmark.shield", "chevron.right", "chevron.up",
    "clock.history", "compose", "crown", "dashboard", "dashboard.fill", "delete.left", "device.phone",
    "doc.on.doc", "doc.text", "dollarsign.circle", "ellipsis.circle", "employee.badge", "envelope",
    "envelope.badge", "envelope.open", "exclamationmark.shield", "exclamationmark.triangle",
    "externaldrive", "eye.slash", "faceid", "files.stack", "folder", "gear", "globe", "hand.raised",
    "hand.thumbsdown", "heart", "help.bubble", "iphone", "iphone.slash", "key", "lifepreserver",
    "line.3.horizontal", "link.badge.plus", "link.chain", "live", "live.fill", "location", "lock",
    "lock.shield", "mic", "minus.circle", "notification", "number", "paintbrush", "paintpalette",
    "paperplane", "pause", "person.2", "person.2.badge.gearshape", "person.2.wave.2",
    "person.3.sequence", "person.3.sequence.fill", "person.badge.key", "person.badge.shield.checkmark",
    "person.crop.circle.badge.clock", "person.wave.2", "phone", "phone.down", "photo",
    "photo.on.rectangle", "pin", "platform", "platform.fill", "play", "plus", "plus.circle",
    "profile.user", "questionmark.circle", "safari", "search", "send", "shield.checkered",
    "slider.horizontal.3", "sparkles", "speaker.wave.2", "square.and.arrow.up", "star", "team",
    "team.alert", "team.broadcast", "team.fill", "team.headset", "touchid", "trash", "waveform",
    "xmark.circle",
]


def resolve_paths(name: str) -> list[dict[str, str]]:
    if name in ICONS:
        return ICONS[name]
    base = ALIASES.get(name)
    if base and base in ICONS:
        return ICONS[base]
    # minimal fallback dot grid
    return [{"d": "M12 2.25a.75.75 0 0 1 .75.75v.5a.75.75 0 0 1-1.5 0v-.5a.75.75 0 0 1 .75-.75ZM12 18a.75.75 0 0 1 .75.75v.5a.75.75 0 0 1-1.5 0v-.5A.75.75 0 0 1 12 18Z"}]


def svg_for(name: str) -> str:
    paths = resolve_paths(name)
    body = []
    for p in paths:
        rule = p.get("fillRule", "nonzero")
        attr = f' fill-rule="{rule}"' if rule == "evenodd" else ""
        body.append(f'  <path d="{p["d"]}"{attr}/>')
    return (
        '<?xml version="1.0" encoding="UTF-8"?>\n'
        '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor">\n'
        + "\n".join(body)
        + "\n</svg>\n"
    )


def imageset_contents(filename: str) -> dict:
    return {
        "images": [{"filename": filename, "idiom": "universal"}],
        "info": {"author": "xcode", "version": 1},
        "properties": {
            "preserves-vector-representation": True,
            "template-rendering-intent": "template",
        },
    }


def safe_filename(name: str) -> str:
    return name.replace("/", "_")


def main() -> None:
    OUT.mkdir(parents=True, exist_ok=True)
    (OUT / "Contents.json").write_text(json.dumps({"info": {"author": "xcode", "version": 1}}))
    manifest = []
    for glyph in CATALOG_GLYPHS:
        fname = f"{safe_filename(glyph)}.svg"
        imageset = OUT / f"{safe_filename(glyph)}.imageset"
        imageset.mkdir(exist_ok=True)
        (imageset / fname).write_text(svg_for(glyph))
        (imageset / "Contents.json").write_text(json.dumps(imageset_contents(fname), indent=2))
        manifest.append(glyph)
    (OUT / "manifest.json").write_text(json.dumps(manifest, indent=2))
    print(f"Generated {len(manifest)} SVG imagesets in {OUT}")


if __name__ == "__main__":
    main()
