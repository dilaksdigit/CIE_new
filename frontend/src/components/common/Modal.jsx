import React from 'react';
const Modal = ({ isOpen, children }) => isOpen ? <div className="modal">{children}</div> : null;
export default Modal;
