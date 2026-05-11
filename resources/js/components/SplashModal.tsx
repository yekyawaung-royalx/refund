import { useEffect, useState } from "react";

const IMAGE_URL = "https://royalx.net/images/splash-image.jpg";

export default function SplashModal({ duration = 9 }) {
  const [visible, setVisible] = useState(false);
  const [timeLeft, setTimeLeft] = useState(duration);

  useEffect(() => {
    setVisible(true);

    const interval = setInterval(() => {
      setTimeLeft((prev) => {
        if (prev <= 1) {
          clearInterval(interval);
          setVisible(false);
          return 0;
        }
        return prev - 1;
      });
    }, 1000);

    return () => clearInterval(interval);
  }, []);

  return (
    <div className={`splash-modal ${visible ? "show" : ""}`}>
      <div className="splash-modal-content">

        <button className="close-btn" onClick={() => setVisible(false)}>
          ×
        </button>

        <img src={IMAGE_URL} alt="Splash" />

        <div className="timer">
          <span className="time-num">{timeLeft}</span>
          <span className="time-unit">s</span>
        </div>

      </div>
    </div>
  );
}