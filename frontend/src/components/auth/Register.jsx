// SOURCE: CIE_v232_Developer_Amendment_Pack_v2.docx Section 3.1
// SOURCE: CIE_v232_UI_Restructure_Instructions.docx Section 1.4
// Spec mandates exactly 2 seed accounts. No registration UI permitted.
import { Navigate } from 'react-router-dom';

export default function Register() {
  return <Navigate to="/login" replace />;
}
