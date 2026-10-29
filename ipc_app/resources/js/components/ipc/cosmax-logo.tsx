import logo from '../../../images/cosmaxring.png';

export function CosmaxLogo() {
    return (
        <span className="relative block h-14 w-8 shrink-0 overflow-hidden">
            <img src={logo} alt="COSMAX" className="absolute -top-[7px] -left-[19px] w-[68px] max-w-none" />
        </span>
    );
}
