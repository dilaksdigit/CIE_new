from urllib.parse import urlparse, urlunparse


def normalise_url(url: str) -> str:
    """
    SOURCE: CIE_Master_Developer_Build_Spec.docx §9.3 (verbatim shape).
    """
    if not url:
        return ""
    p = urlparse(url.lower().strip())
    return urlunparse((p.scheme, p.netloc, p.path.rstrip("/"), "", "", ""))
