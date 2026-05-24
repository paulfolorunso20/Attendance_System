const QRCode = require("qrcode");

const data = process.argv[2] || "";

QRCode.toString(data, {
  errorCorrectionLevel: "M",
  margin: 4,
  type: "svg",
  width: 320,
}).then((svg) => {
  process.stdout.write(svg);
}).catch(() => {
  process.exit(1);
});
