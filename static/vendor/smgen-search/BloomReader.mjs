export class BloomReader
{
	constructor({ size, hashes })
	{
		if(!Number.isInteger(size) || size <= 0) throw new Error('size must be > 0');
		if(!Number.isInteger(hashes) || hashes <= 0) throw new Error('hashes must be > 0');
		this.m = size;
		this.k = hashes;
		this.bits = new Uint8Array(Math.ceil(size / 8));
		this.n = 0;
	}

	static fromBinary(buffer)
	{
		const bytes = new Uint8Array(buffer);
		const view = new DataView(bytes.buffer, bytes.byteOffset, bytes.byteLength);
		const reader = new this({size: view.getUint32(0, true), hashes: view.getUint32(4, true)});
		reader.n = view.getUint32(8, true);
		reader.bits = bytes.slice(12);
		return reader;
	}

	has(value)
	{
		const [h1, h2] = this.doubleHashSeeds(String(value));
		for(let i = 0; i < this.k; i++)
		{
			const idx = (h1 + i * h2) % this.m;
			if(!this.getBit(idx)) return false;
		}
		return true;
	}

	doubleHashSeeds(str)
	{
		const h1 = this.fnv1a32(str) >>> 0;
		let h2 = (this.xorshift32(str) | 1) >>> 0;
		if(h2 === 0) h2 = 1;
		return [h1 % this.m, h2 % this.m];
	}

	fnv1a32(str)
	{
		let h = 0x811c9dc5 >>> 0;
		for(const ch of str)
		{
			h ^= ch.codePointAt(0);
			h = (h + ((h << 1) + (h << 4) + (h << 7) + (h << 8) + (h << 24))) >>> 0;
		}
		return h >>> 0;
	}

	xorshift32(str)
	{
		let x = 0x9e3779b9 ^ str.length;
		for(const ch of str)
		{
			x ^= ch.codePointAt(0) + 0x7ed55d16 + (x << 12);
			x = (x ^ (x >>> 19)) >>> 0;
		}
		x ^= x << 13; x >>>= 0;
		x ^= x >>> 17; x >>>= 0;
		x ^= x << 5; x >>>= 0;
		return x >>> 0;
	}

	getBit(i)
	{
		const byte = (i / 8) | 0;
		const mask = 1 << (i % 8);
		return (this.bits[byte] & mask) !== 0;
	}
}
